<?php

namespace BergPlaza\Ebook\Tools;

use BergPlaza\Ebook\Enums\AgeRatingEnum;
use BergPlaza\Ebook\Enums\MangaEnum;

class ComicMeta
{
    /** @var string[] */
    protected ?array $characters = null;

    /** @var string[] */
    protected ?array $teams = null;

    /** @var string[] */
    protected ?array $locations = null;

    /** @var string[] */
    protected ?array $editors = null;

    public function __construct(
        protected ?string $alternateSeries = null,
        protected ?int $alternateNumber = null,
        protected ?string $alternateCount = null,
        protected ?int $count = null,
        protected ?int $volume = null,
        protected ?string $storyArc = null,
        protected ?int $storyArcNumber = null,
        protected ?string $seriesGroup = null,
        protected ?string $imprint = null,
        protected ?string $scanInformation = null,
        protected ?string $notes = null,
        protected ?float $communityRating = null,
        protected ?bool $isBlackAndWhite = null,
        protected ?AgeRatingEnum $ageRating = null,
        protected ?string $review = null,
        protected ?string $web = null,
        protected ?MangaEnum $manga = null,
        protected ?string $mainCharacterOrTeam = null,
        protected ?string $format = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function characters(): array
    {
        return $this->characters;
    }

    /**
     * @return string[]
     */
    public function teams(): array
    {
        return $this->teams;
    }

    /**
     * @return string[]
     */
    public function locations(): array
    {
        return $this->locations;
    }

    public function alternateSeries(): ?string
    {
        return $this->alternateSeries;
    }

    public function alternateNumber(): ?int
    {
        return $this->alternateNumber;
    }

    public function alternateCount(): ?string
    {
        return $this->alternateCount;
    }

    public function count(): ?int
    {
        return $this->count;
    }

    public function volume(): ?int
    {
        return $this->volume;
    }

    public function storyArc(): ?string
    {
        return $this->storyArc;
    }

    public function storyArcNumber(): ?int
    {
        return $this->storyArcNumber;
    }

    public function seriesGroup(): ?string
    {
        return $this->seriesGroup;
    }

    public function imprint(): ?string
    {
        return $this->imprint;
    }

    public function scanInformation(): ?string
    {
        return $this->scanInformation;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function communityRating(): ?float
    {
        return $this->communityRating;
    }

    public function isBlackAndWhite(): ?bool
    {
        return $this->isBlackAndWhite;
    }

    public function ageRating(): ?AgeRatingEnum
    {
        return $this->ageRating;
    }

    public function review(): ?string
    {
        return $this->review;
    }

    public function web(): ?string
    {
        return $this->web;
    }

    public function manga(): ?MangaEnum
    {
        return $this->manga;
    }

    public function mainCharacterOrTeam(): ?string
    {
        return $this->mainCharacterOrTeam;
    }

    public function format(): ?string
    {
        return $this->format;
    }

    /**
     * @return string[]
     */
    public function editors(): array
    {
        return $this->editors;
    }

    /**
     * @param  string[]  $characters
     */
    public function setCharacters(array $characters): self
    {
        $this->characters = $characters;

        return $this;
    }

    /**
     * @param  string[]  $teams
     */
    public function setTeams(array $teams): self
    {
        $this->teams = $teams;

        return $this;
    }

    /**
     * @param  string[]  $locations
     */
    public function setLocations(array $locations): self
    {
        $this->locations = $locations;

        return $this;
    }

    /**
     * @param  string[]  $editors
     */
    public function setEditors(array $editors): self
    {
        $this->editors = $editors;

        return $this;
    }
}
