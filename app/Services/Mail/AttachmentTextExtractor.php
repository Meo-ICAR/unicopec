<?php

namespace App\Services\Mail;

use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Process\Process;

/**
 * Estrae il testo dagli allegati delle email (PDF anche scansionati, Word, Excel, PowerPoint, immagini, p7m delle PEC, testo e HTML)
 * usando i programmi installati sul server: pdftotext, ocrmypdf/tesseract, pandoc, LibreOffice e openssl.
 * Gli allegati sono input non fidato: nessuna shell, tempo massimo per ogni programma, dimensione massima del file e del testo.
 */
class AttachmentTextExtractor
{
    public const MAX_BYTES = 26214400;

    public const MAX_CHARS = 200000;

    private const PANDOC_FORMATS = ['docx' => 'docx', 'odt' => 'odt', 'rtf' => 'rtf'];

    private const SPREADSHEETS = ['xls', 'xlsx', 'ods'];

    private const WORD_LEGACY = ['doc'];

    private const SLIDES = ['ppt', 'pptx', 'odp'];

    private const IMAGES = ['png', 'jpg', 'jpeg', 'tif', 'tiff', 'bmp', 'gif', 'webp'];

    private const PLAIN = ['txt', 'csv', 'xml', 'json', 'eml', 'log', 'md'];

    private const HTML = ['htm', 'html'];

    /**
     * Estrae il testo di un file. Restituisce null se il tipo non è supportato.
     *
     * @return array{text: string, method: string}|null
     */
    public function extract(string $path, string $fileName, int $depth = 0): ?array
    {
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('File mancante o troppo grande');
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $result = match (true) {
            $extension === 'p7m' && $depth < 2 => $this->fromSignedMessage($path, $fileName, $depth),
            $extension === 'pdf' => $this->fromPdf($path),
            isset(self::PANDOC_FORMATS[$extension]) => $this->fromPandoc($path, self::PANDOC_FORMATS[$extension]),
            in_array($extension, self::SPREADSHEETS, true) => $this->fromSpreadsheet($path, $extension),
            in_array($extension, self::WORD_LEGACY, true) => $this->fromLibreOfficeText($path, $extension),
            in_array($extension, self::SLIDES, true) => $this->fromSlides($path, $extension),
            in_array($extension, self::IMAGES, true) => $this->fromImage($path),
            in_array($extension, self::PLAIN, true) => ['text' => (string) file_get_contents($path, false, null, 0, 2097152), 'method' => 'testo'],
            in_array($extension, self::HTML, true) => ['text' => $this->fromHtml($path), 'method' => 'html'],
            default => null,
        };

        if ($result === null) {
            return null;
        }

        return ['text' => $this->normalize($result['text']), 'method' => $result['method']];
    }

    /**
     * Estrae il testo di un allegato e lo salva tra le proprietà del file (extracted_text, extracted_method, extracted_at, extracted_error).
     */
    public function extractMedia(Media $media): void
    {
        $media->forgetCustomProperty('extracted_error');

        try {
            $result = $this->extract($media->getPath(), $media->file_name);

            if ($result === null) {
                $media->setCustomProperty('extracted_method', 'non supportato');
            } else {
                $media->setCustomProperty('extracted_text', $result['text']);
                $media->setCustomProperty('extracted_method', $result['method']);
            }

            $media->setCustomProperty('extracted_at', now()->toIso8601String());
        } catch (\Throwable $e) {
            $media->setCustomProperty('extracted_error', mb_substr($e->getMessage(), 0, 300));
        }

        $media->save();
    }

    /** @return array{text: string, method: string} */
    private function fromSignedMessage(string $path, string $fileName, int $depth): array
    {
        $dir = $this->tempDir();
        $out = $dir.'/contenuto';

        try {
            $extracted = false;

            foreach ([['smime', 'DER'], ['cms', 'DER'], ['smime', 'PEM'], ['cms', 'PEM']] as [$command, $form]) {
                $process = new Process(['openssl', $command, '-verify', '-noverify', '-inform', $form, '-in', $path, '-out', $out], null, null, null, 60);
                $process->run();

                if ($process->isSuccessful() && is_file($out) && filesize($out) > 0) {
                    $extracted = true;
                    break;
                }
            }

            if (! $extracted) {
                throw new RuntimeException('Contenuto del p7m non estraibile');
            }

            $innerName = substr($fileName, 0, -4);
            $inner = $dir.'/'.str_replace(['/', '\\'], '-', $innerName);
            rename($out, $inner);

            $result = $this->extract($inner, $innerName, $depth + 1);

            return $result === null
                ? ['text' => '', 'method' => 'p7m (contenuto non supportato)']
                : ['text' => $result['text'], 'method' => 'p7m + '.$result['method']];
        } finally {
            $this->removeDir($dir);
        }
    }

    /** @return array{text: string, method: string} */
    private function fromPdf(string $path): array
    {
        $text = $this->run(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);

        if (mb_strlen(preg_replace('/\s+/u', '', $text) ?? '') >= 40) {
            return ['text' => $text, 'method' => 'pdf'];
        }

        $dir = $this->tempDir();

        try {
            $ocr = $dir.'/ocr.pdf';
            $this->run(['ocrmypdf', '-l', 'ita+eng', '--skip-text', '--optimize', '0', $path, $ocr], 600);

            return ['text' => $this->run(['pdftotext', '-layout', '-enc', 'UTF-8', $ocr, '-']), 'method' => 'pdf + ocr'];
        } finally {
            $this->removeDir($dir);
        }
    }

    /** @return array{text: string, method: string} */
    private function fromPandoc(string $path, string $format): array
    {
        return ['text' => $this->run(['pandoc', '-f', $format, '-t', 'plain', '--wrap=none', $path]), 'method' => $format];
    }

    /** @return array{text: string, method: string} */
    private function fromSpreadsheet(string $path, string $extension): array
    {
        $dir = $this->tempDir();

        try {
            $source = $dir.'/foglio.'.$extension;
            copy($path, $source);
            $filter = 'csv:Text - txt - csv (StarCalc):44,34,76,1,,0,false,true,false,false,false,-1';
            $this->libreOffice($dir, $source, $filter);

            $files = glob($dir.'/foglio-*.csv') ?: [];
            sort($files);
            $text = '';

            foreach ($files as $file) {
                $sheet = substr(basename($file, '.csv'), strlen('foglio-'));
                $text .= "## Foglio: {$sheet}\n".file_get_contents($file, false, null, 0, 1048576)."\n";
            }

            return ['text' => $text, 'method' => $extension.' (tutti i fogli)'];
        } finally {
            $this->removeDir($dir);
        }
    }

    /** @return array{text: string, method: string} */
    private function fromLibreOfficeText(string $path, string $extension): array
    {
        $dir = $this->tempDir();

        try {
            $source = $dir.'/documento.'.$extension;
            copy($path, $source);
            $this->libreOffice($dir, $source, 'txt:Text (encoded):UTF8');

            return ['text' => (string) @file_get_contents($dir.'/documento.txt'), 'method' => $extension];
        } finally {
            $this->removeDir($dir);
        }
    }

    /** @return array{text: string, method: string} */
    private function fromSlides(string $path, string $extension): array
    {
        $dir = $this->tempDir();

        try {
            $source = $dir.'/presentazione.'.$extension;
            copy($path, $source);
            $this->libreOffice($dir, $source, 'pdf');

            return ['text' => $this->run(['pdftotext', '-layout', '-enc', 'UTF-8', $dir.'/presentazione.pdf', '-']), 'method' => $extension];
        } finally {
            $this->removeDir($dir);
        }
    }

    /** @return array{text: string, method: string} */
    private function fromImage(string $path): array
    {
        return ['text' => $this->run(['tesseract', $path, 'stdout', '-l', 'ita+eng'], 180), 'method' => 'ocr immagine'];
    }

    private function fromHtml(string $path): string
    {
        $html = (string) file_get_contents($path, false, null, 0, 2097152);
        $html = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/tr|/li|/h[1-6])\b[^>]*>#i', "\n", $html) ?? $html;

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function libreOffice(string $dir, string $source, string $filter): void
    {
        $this->run([
            'soffice', '--headless', '--norestore', '--nolockcheck',
            '-env:UserInstallation=file://'.$dir.'/profilo',
            '--convert-to', $filter, '--outdir', $dir, $source,
        ], 180);
    }

    /** @param array<int, string> $command */
    private function run(array $command, int $timeout = 120): string
    {
        $process = new Process($command, null, ['HOME' => sys_get_temp_dir()], null, $timeout);
        $process->mustRun();

        return $process->getOutput();
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\f", "\0"], ["\n", "\n", "\n", ''], $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return mb_substr(trim($text), 0, self::MAX_CHARS);
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir().'/attx_'.bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            $entry = $dir.'/'.$name;
            is_dir($entry) && ! is_link($entry) ? $this->removeDir($entry) : @unlink($entry);
        }

        @rmdir($dir);
    }
}
