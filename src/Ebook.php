<?php

namespace BergPlaza\Ebook;

use DateTime;
use BergPlaza\Archive\Archive;
use BergPlaza\Archive\ArchiveZipCreate;
use BergPlaza\Archive\Readers\BaseArchive;
use BergPlaza\Audio\Audio;
use BergPlaza\Ebook\Creator\EbookCreator;
use BergPlaza\Ebook\Enums\EbookFormatEnum;
use BergPlaza\Ebook\Formats\Audio\AudiobookModule;
use BergPlaza\Ebook\Formats\Cba\CbaModule;
use BergPlaza\Ebook\Formats\Djvu\DjvuModule;
use BergPlaza\Ebook\Formats\EbookMetadata;
use BergPlaza\Ebook\Formats\EbookModule;
use BergPlaza\Ebook\Formats\Epub\EpubModule;
use BergPlaza\Ebook\Formats\Fb2\Fb2Module;
use BergPlaza\Ebook\Formats\Mobi\MobiModule;
use BergPlaza\Ebook\Formats\Pdf\PdfModule;
use BergPlaza\Ebook\Tools\BookAuthor;
use BergPlaza\Ebook\Tools\BookIdentifier;
use BergPlaza\Ebook\Tools\MetaTitle;

class Ebook
{
    protected ?string $title = null;

    protected ?MetaTitle $metaTitle = null;

    protected ?BookAuthor $authorMain = null;

    /** @var BookAuthor[] */
    protected array $authors = [];

    protected ?string $description = null;

    protected ?string $descriptionHtml = null;

    protected ?string $publisher = null;

    /** @var BookIdentifier[] */
    protected array $identifiers = [];

    protected ?DateTime $publishDate = null;

    protected ?string $language = null;

    /** @var string[] */
    protected array $tags = [];

    protected ?string $series = null;

    protected ?int $volume = null;

    protected ?string $copyright = null;

    protected ?EbookFormatEnum $format = null;

    protected ?EbookCover $cover = null;

    protected ?int $wordsCount = null;

    protected ?int $pagesCount = null;

    protected bool $countsParsed = false;

    protected ?float $execTime = null;

    /** @var array<string, mixed> */
    protected array $extras = [];

    protected function __construct(
        protected string $path,
        protected string $filename,
        protected string $basename,
        protected string $extension,
        protected ?BaseArchive $archive = null,
        protected ?Audio $audio = null,
        protected bool $isArchive = false,
        protected bool $isAudio = false,
        protected bool $isMobi = false,
        protected bool $isBadFile = false,
        protected ?EbookMetadata $metadata = null,
        protected bool $hasMetadata = false,
    ) {
    }

    /**
     * Read an ebook file.
     */
    public static function read(string $path): ?self
    {
        $start = microtime(true);
        $self = self::parseFile($path);

        $format = match ($self->format) {
            EbookFormatEnum::AUDIOBOOK => $self->audiobook(),
            EbookFormatEnum::CBA => $self->cba(),
            EbookFormatEnum::DJVU => $self->djvu(),
            EbookFormatEnum::EPUB => $self->epub(),
            EbookFormatEnum::FB2 => $self->fb2(),
            EbookFormatEnum::MOBI => $self->mobi(),
            EbookFormatEnum::PDF => $self->pdf(),
            default => null,
        };

        if ($format === null) {
            return null;
        }

        $self->metadata = EbookMetadata::make($format);
        $self->convertEbook();
        $self->cover = $self->metadata->getModule()->toCover();
        $self->metaTitle = MetaTitle::make($self);

        $time = microtime(true) - $start;
        $self->execTime = (float) number_format((float) $time, 5, '.', '');

        return $self;
    }

    /**
     * Check if an ebook file is valid.
     */
    public static function isValid(string $path): bool
    {
        $self = self::parseFile($path);

        return ! $self->isBadFile;
    }

    /**
     * Create an ebook file.
     */
    public static function create(string $path): ArchiveZipCreate
    {
        return EbookCreator::create($path);
    }

    /**
     * Parse an ebook file.
     */
    private static function parseFile(string $path): Ebook
    {
        $basename = pathinfo($path, PATHINFO_BASENAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $cbaExtensions = ['cbz', 'cbr', 'cb7', 'cbt'];
        $archiveExtensions = ['epub', 'pdf', ...$cbaExtensions];
        $audiobookExtensions = ['mp3', 'm4a', 'm4b', 'flac', 'ogg'];
        $mobipocketExtensions = ['mobi', 'azw', 'azw3', 'azw4', 'kf8', 'kfx', 'prc', 'tpz'];
        $extrasExtensions = ['lrf', 'lrx', 'fb2', 'rtf', 'ibooks', 'pdb', 'snb', 'djvu', 'djv'];
        $allowExtensions = [...$archiveExtensions, ...$audiobookExtensions, ...$mobipocketExtensions, ...$extrasExtensions];

        if (! file_exists($path)) {
            throw new \Exception("File not found: {$path}");
        }

        if ($extension && ! in_array($extension, $allowExtensions)) {
            throw new \Exception("Unknown archive type: {$extension}");
        }

        $self = new self($path, $filename, $basename, $extension);

        $self->format = match ($extension) {
            'azw' => $self->format = EbookFormatEnum::MOBI,
            'azw3' => $self->format = EbookFormatEnum::MOBI,
            'djvu' => $self->format = EbookFormatEnum::DJVU,
            'djv' => $self->format = EbookFormatEnum::DJVU,
            'epub' => $self->format = EbookFormatEnum::EPUB,
            'mobi' => $self->format = EbookFormatEnum::MOBI,
            'lrf' => $self->format = EbookFormatEnum::MOBI,
            'kf8' => $self->format = EbookFormatEnum::MOBI,
            'kfx' => $self->format = EbookFormatEnum::MOBI,
            'prc' => $self->format = EbookFormatEnum::MOBI,
            // 'rtf' => $self->format = EbookFormatEnum::RTF,
            'fb2' => $self->format = EbookFormatEnum::FB2,
            'pdf' => $self->format = EbookFormatEnum::PDF,
            default => null,
        };

        if (! $self->format) {
            if (in_array($extension, $cbaExtensions)) {
                $self->format = EbookFormatEnum::CBA;
            } elseif (in_array($extension, $audiobookExtensions)) {
                $self->format = EbookFormatEnum::AUDIOBOOK;
            } else {
                // throw new \Exception("Unknown archive type: {$extension}");
            }
        }

        if (in_array($extension, $archiveExtensions)) {
            $self->isArchive = true;
        }

        if (in_array($extension, $audiobookExtensions)) {
            $self->isAudio = true;
        }

        if ($self->isArchive) {
            try {
                $archive = Archive::read($path);
                $self->archive = $archive;
            } catch (\Throwable $th) {
                error_log("Error reading archive: {$path}");
                $self->isBadFile = true;
            }
        }

        if ($self->isAudio) {
            $self->audio = Audio::get($path);
        }

        return $self;
    }

    private function audiobook(): EbookModule
    {
        return AudiobookModule::make($this);
    }

    private function cba(): EbookModule
    {
        return CbaModule::make($this);
    }

    private function djvu(): EbookModule
    {
        return DjvuModule::make($this);
    }

    private function epub(): EbookModule
    {
        return EpubModule::make($this);
    }

    private function fb2(): EbookModule
    {
        return Fb2Module::make($this);
    }

    private function mobi(): EbookModule
    {
        $this->isMobi = true;

        return MobiModule::make($this);
    }

    private function pdf(): EbookModule
    {
        return PdfModule::make($this);
    }

    private function convertEbook(): self
    {
        $ebook = $this->metadata->getModule()->toEbook();

        $this->title = $ebook->getTitle();
        $this->metaTitle = $ebook->getMetaTitle();
        $this->authorMain = $ebook->getAuthorMain();
        $this->authors = $ebook->getAuthors();
        $this->description = $ebook->getDescription();
        $this->descriptionHtml = $ebook->getDescriptionHtml();
        $this->publisher = $ebook->getPublisher();
        $this->identifiers = $ebook->getIdentifiers();
        $this->publishDate = $ebook->getPublishDate();
        $this->language = $ebook->getLanguage();
        $this->tags = $ebook->getTags();
        $this->series = $ebook->getSeries();
        $this->volume = $ebook->getVolume();
        $this->copyright = $ebook->getCopyright();

        return $this;
    }

    private function convertCounts(): self
    {
        $this->countsParsed = true;
        $counts = $this->metadata->getModule()->toCounts();

        $this->wordsCount = $counts->getWordsCount();
        $this->pagesCount = $counts->getPagesCount();

        return $this;
    }

    public static function wordsByPage(): int
    {
        return 250;
    }

    public function toXml(string $path): ?string
    {
        if ($this->isBadFile) {
            return null;
        }

        $ebook = $this->archive->find($path);
        $content = $this->archive->getContents($ebook);

        return $content;
    }

    /**
     * Title of the book.
     */
    public function getTitle(): ?string
    {

        return $this->title;
    }

    /**
     * Title metadata of the book with slug, sort title, series slug, etc.
     * Can be null if the title is null.
     */
    public function getMetaTitle(): ?MetaTitle
    {
        return $this->metaTitle;
    }

    /**
     * First author of the book (useful if you need to display only one author).
     */
    public function getAuthorMain(): ?BookAuthor
    {
        return $this->authorMain;
    }

    /**
     * All authors of the book.
     *
     * @return BookAuthor[]
     */
    public function getAuthors(): array
    {

        return $this->authors;
    }

    /**
     * Description of the book, without HTML.
     *
     * If original description has HTML, all HTML will be removed and text will be trimmed.
     * You can use `getDescriptionHtml()` to get the original description sanitized.
     *
     * @param  int|null  $limit  Limit the length of the description.
     */
    public function getDescription(int $limit = null): ?string
    {
        if ($limit) {
            return $this->limitLength($this->description, $limit);
        }

        return $this->description;
    }

    /**
     * Description of the book with HTML sanitized.
     *
     * If original description doesn't have HTML, it will be the same as `getDescription()`.
     */
    public function getDescriptionHtml(): ?string
    {
        return $this->descriptionHtml;
    }

    /**
     * Publisher of the book.
     */
    public function getPublisher(): ?string
    {

        return $this->publisher;
    }

    /**
     * Identifiers of the book.
     *
     * @return BookIdentifier[]
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    /**
     * Publish date of the book.
     */
    public function getPublishDate(): ?DateTime
    {
        return $this->publishDate;
    }

    /**
     * Language of the book.
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * Tags of the book.
     *
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Series of the book.
     */
    public function getSeries(): ?string
    {

        return $this->series;
    }

    /**
     * Volume of the book.
     */
    public function getVolume(): ?int
    {
        return $this->volume;
    }

    /**
     * Copyright of the book.
     */
    public function getCopyright(int $limit = null): ?string
    {
        if ($limit) {
            return $this->limitLength($this->copyright, $limit);
        }

        return $this->copyright;
    }

    /**
     * Physical path to the ebook.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Filename of the ebook, e.g. `The Clan of the Cave Bear`.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Basename of the ebook, e.g. `The Clan of the Cave Bear.epub`.
     */
    public function getBasename(): string
    {
        return $this->basename;
    }

    /**
     * Extension of the ebook, e.g. `epub`.
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * Archive reader, from `kiwilan/php-archive`.
     *
     * @docs https://github.com/kiwilan/php-archive
     */
    public function getArchive(): ?BaseArchive
    {
        return $this->archive;
    }

    /**
     * Audio reader, from `kiwilan/php-audio`.
     *
     * @docs https://github.com/kiwilan/php-audio
     */
    public function getAudio(): ?Audio
    {
        return $this->audio;
    }

    /**
     * Whether the ebook is an audio.
     */
    public function isAudio(): bool
    {
        return $this->isAudio;
    }

    /**
     * Whether the ebook is a mobi.
     */
    public function isMobi(): bool
    {
        return ! $this->isAudio;
    }

    /**
     * Whether the ebook is a bad file.
     */
    public function isBadFile(): bool
    {
        return $this->isBadFile;
    }

    /**
     * Whether the ebook is an archive.
     */
    public function isArchive(): bool
    {
        return $this->isArchive;
    }

    /**
     * Whether the ebook has metadata.
     */
    public function hasMetadata(): bool
    {
        return $this->hasMetadata;
    }

    /**
     * Format of the ebook.
     */
    public function getFormat(): ?EbookFormatEnum
    {
        return $this->format;
    }

    /**
     * Metadata of the ebook.
     */
    public function getMetadata(): ?EbookMetadata
    {
        return $this->metadata;
    }

    /**
     * Cover of the ebook.
     */
    public function getCover(): ?EbookCover
    {
        return $this->cover;
    }

    /**
     * Word count of the ebook.
     */
    public function getWordsCount(): ?int
    {
        if ($this->wordsCount) {
            return $this->wordsCount;
        }

        if (! $this->countsParsed) {
            $this->convertCounts();
        }

        return $this->wordsCount;
    }

    /**
     * Page count of the ebook.
     */
    public function getPagesCount(): ?int
    {
        if ($this->pagesCount) {
            return $this->pagesCount;
        }

        if (! $this->countsParsed) {
            $this->convertCounts();
        }

        return $this->pagesCount;
    }

    /**
     * Execution time for parsing the ebook.
     */
    public function getExecTime(): ?float
    {
        return $this->execTime;
    }

    /**
     * Extras of the ebook.
     *
     * @return array<string, mixed>
     */
    public function getExtras(): array
    {
        return $this->extras;
    }

    /**
     * Get key from `extras` safely.
     */
    public function getExtra(string $key): mixed
    {
        if (! array_key_exists($key, $this->extras)) {
            return null;
        }

        return $this->extras[$key];
    }

    /**
     * Whether the ebook has a cover.
     */
    public function hasCover(): bool
    {
        return $this->cover !== null;
    }

    private function limitLength(?string $string, int $length): ?string
    {
        if (! $string) {
            return null;
        }

        if (mb_strlen($string) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length - 1).'…';
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setMetaTitle(Ebook $ebook): self
    {
        if (! $ebook->getTitle()) {
            return $this;
        }

        $this->metaTitle = MetaTitle::make($ebook);

        return $this;
    }

    public function setAuthorMain(?BookAuthor $authorMain): self
    {
        $this->authorMain = $authorMain;

        return $this;
    }

    public function setAuthor(BookAuthor $author): self
    {
        $this->authors = [
            $author,
            ...$this->authors,
        ];

        if (! $this->authorMain) {
            $this->authorMain = $author;
        }

        return $this;
    }

    /**
     * @param  BookAuthor[]  $authors
     */
    public function setAuthors(array $authors): self
    {

        $this->authors = $authors;

        if (! $this->authorMain && count($this->authors) > 0) {
            $this->authorMain = reset($this->authors);
        }

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setDescriptionHtml(?string $descriptionHtml): self
    {
        $this->descriptionHtml = $descriptionHtml;

        return $this;
    }

    public function setPublisher(?string $publisher): self
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function setIdentifier(BookIdentifier $identifier): self
    {
        $key = $identifier->getScheme() ?? uniqid();

        if (array_key_exists($key, $this->identifiers)) {
            $key = uniqid();
            $this->identifiers[$key] = $identifier;

            return $this;
        }

        $this->identifiers[$key] = $identifier;

        return $this;
    }

    /**
     * @param  BookIdentifier[]  $identifiers
     */
    public function setIdentifiers(array $identifiers): self
    {
        $this->identifiers = $identifiers;

        return $this;
    }

    public function setPublishDate(?DateTime $publishDate): self
    {
        $this->publishDate = $publishDate;

        return $this;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function setTag(?string $tag): self
    {
        if (! $tag) {
            return $this;
        }

        $tag = trim($tag);
        $this->tags = [
            $tag,
            ...$this->tags,
        ];

        return $this;
    }

    /**
     * @param  string[]|null  $tags
     */
    public function setTags(?array $tags): self
    {
        if (! $tags) {
            return $this;
        }

        $this->tags = $tags;

        return $this;
    }

    public function setSeries(?string $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function setVolume(int|string|null $volume): self
    {
        if (is_string($volume)) {
            $volume = intval($volume);
        }

        $this->volume = $volume;

        return $this;
    }

    public function setCopyright(?string $copyright): self
    {
        $this->copyright = $copyright;

        return $this;
    }

    public function setWordsCount(?int $wordsCount): self
    {
        $this->wordsCount = $wordsCount;

        return $this;
    }

    public function setPagesCount(?int $pagesCount): self
    {
        $this->pagesCount = $pagesCount;

        return $this;
    }

    public function setHasMetadata(bool $hasMetadata): self
    {
        $this->hasMetadata = $hasMetadata;

        return $this;
    }

    public function setExtra(mixed $value, string $key = null): self
    {
        if (! $key) {
            $this->extras[] = $value;

            return $this;
        }

        $this->extras[$key] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    public function setExtras(array $extras): self
    {
        $this->extras = $extras;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'authorMain' => $this->authorMain?->getName(),
            'authors' => array_map(fn (BookAuthor $author) => $author->getName(), $this->authors),
            'description' => $this->description,
            'descriptionHtml' => $this->descriptionHtml,
            'publisher' => $this->publisher,
            'identifiers' => array_map(fn (BookIdentifier $identifier) => $identifier->toArray(), $this->identifiers),
            'date' => $this->publishDate?->format('Y-m-d H:i:s'),
            'language' => $this->language,
            'tags' => $this->tags,
            'series' => $this->series,
            'volume' => $this->volume,
            'wordsCount' => $this->wordsCount,
            'pagesCount' => $this->pagesCount,
            'path' => $this->path,
            'filename' => $this->filename,
            'basename' => $this->basename,
            'extension' => $this->extension,
            'format' => $this->format,
            'metadata' => $this->metadata?->toArray(),
            'cover' => $this->cover?->toArray(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function __toString(): string
    {
        return "{$this->path} ({$this->format?->value})";
    }
}
