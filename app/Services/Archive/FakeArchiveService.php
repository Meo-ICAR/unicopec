<?php

namespace App\Services\Archive;

/** Servizio finto: non esce nulla dall'app, risponde con un identificativo ricavato dal contenuto. Da sostituire con il conservatore scelto. */
class FakeArchiveService implements ArchiveService
{
    public function send(ArchivePackage $package): string
    {
        return 'FAKE-'.substr(sha1(json_encode([$package->emailId, $package->files, $package->metadata, $package->supplement])), 0, 16);
    }
}
