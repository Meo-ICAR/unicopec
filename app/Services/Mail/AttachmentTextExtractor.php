<?php

namespace App\Services\Mail;

use Unico\Core\Documents\AttachmentTextExtractor as CoreAttachmentTextExtractor;

/**
 * Estrae il testo dagli allegati delle email (PDF anche scansionati, Word, Excel, PowerPoint, immagini, p7m delle PEC, testo e HTML).
 * La routine è in unico-core (`Unico\Core\Documents\AttachmentTextExtractor`), condivisa con le altre app: qui resta per i nomi già usati.
 */
class AttachmentTextExtractor extends CoreAttachmentTextExtractor {}
