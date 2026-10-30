<?php

namespace App\Services\Archive;

/** Servizio esterno di archiviazione a norma. Restituisce l'identificativo dell'archiviazione, e lancia un'eccezione se non riesce. */
interface ArchiveService
{
    public function send(ArchivePackage $package): string;
}
