<?php

namespace BergPlaza\Ebook\Formats\Djvu;

use BergPlaza\Ebook\Ebook;
use BergPlaza\Ebook\EbookCover;
use BergPlaza\Ebook\Formats\EbookModule;

class DjvuModule extends EbookModule
{
    public static function make(Ebook $ebook): EbookModule
    {
        $self = new self($ebook);

        return $self;
    }

    public function toEbook(): Ebook
    {
        return $this->ebook;
    }

    public function toCover(): ?EbookCover
    {
        return null;
    }

    public function toCounts(): Ebook
    {
        return $this->ebook;
    }

    public function toArray(): array
    {
        return [];
    }
}
