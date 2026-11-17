<?php

namespace Tests\Unit;

use App\Services\Mail\AttachmentTextExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use ZipArchive;

class AttachmentTextExtractorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/attx_test_'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function requireBinary(string $binary): void
    {
        if ((new Process(['which', $binary]))->run() !== 0) {
            $this->markTestSkipped("{$binary} non installato");
        }
    }

    private function file(string $name, string $content): string
    {
        file_put_contents($path = $this->dir.'/'.$name, $content);

        return $path;
    }

    public function test_legge_testo_e_html_senza_tag(): void
    {
        $extractor = new AttachmentTextExtractor;

        $this->assertSame('Fattura n. 12', $extractor->extract($this->file('a.txt', "Fattura n. 12\r\n"), 'a.txt')['text']);

        $html = $extractor->extract($this->file('b.html', '<style>p{}</style><p>Ciao <b>mondo</b></p><script>x()</script>'), 'b.html');
        $this->assertSame('Ciao mondo', $html['text']);
    }

    public function test_tipo_non_supportato_restituisce_null(): void
    {
        $this->assertNull((new AttachmentTextExtractor)->extract($this->file('a.zip', 'PK'), 'a.zip'));
    }

    public function test_file_mancante_solleva_errore(): void
    {
        $this->expectException(\RuntimeException::class);

        (new AttachmentTextExtractor)->extract($this->dir.'/non-esiste.pdf', 'non-esiste.pdf');
    }

    public function test_legge_un_docx(): void
    {
        $this->requireBinary('pandoc');

        $path = $this->dir.'/a.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Contratto di fornitura</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $result = (new AttachmentTextExtractor)->extract($path, 'a.docx');

        $this->assertSame('Contratto di fornitura', $result['text']);
        $this->assertSame('docx', $result['method']);
    }

    public function test_legge_un_pdf_con_testo(): void
    {
        $this->requireBinary('pdftotext');

        $stream = 'BT /F1 18 Tf 50 700 Td (Offerta servizio mailDocPRO numero 263 del 2025) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        $result = (new AttachmentTextExtractor)->extract($this->file('a.pdf', $pdf), 'a.pdf');

        $this->assertStringContainsString('Offerta servizio mailDocPRO numero 263', $result['text']);
        $this->assertSame('pdf', $result['method']);
    }

    public function test_il_testo_e_limitato(): void
    {
        $result = (new AttachmentTextExtractor)->extract($this->file('grande.txt', str_repeat('a', AttachmentTextExtractor::MAX_CHARS + 500)), 'grande.txt');

        $this->assertSame(AttachmentTextExtractor::MAX_CHARS, mb_strlen($result['text']));
    }
}
