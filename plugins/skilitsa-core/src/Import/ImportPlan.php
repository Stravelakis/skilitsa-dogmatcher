<?php
declare(strict_types=1);

namespace Skilitsa\Core\Import;

/**
 * Everything the importer would write, plus why it might not be safe to.
 *
 * The isSafeToApply() rule is the direct answer to v1's worst failure: it
 * wrote its config whenever the result array was non-empty, and the builder
 * always returned a non-empty array, so a failed fetch silently replaced
 * 397 KB of good data with empty arrays.
 */
final class ImportPlan
{
    /**
     * @param array<string,array<string,string>> $traits    trait_key => row
     * @param array<string,array<string,string>> $questions question_key => row
     * @param list<array<string,string>>         $links     question -> trait edges
     * @param array<string,string>               $templates template_key => body
     * @param array<string,string>               $uiTexts
     * @param array<string,string>               $settings
     * @param array<string,array<string,float>>  $breedTraits breed_key => trait => value
     * @param array<string,array<string,string>> $breedMeta   breed_key => meta row
     * @param list<string>                       $warnings
     * @param list<string>                       $errors
     */
    public function __construct(
        public readonly array $traits = [],
        public readonly array $questions = [],
        public readonly array $links = [],
        public readonly array $templates = [],
        public readonly array $uiTexts = [],
        public readonly array $settings = [],
        public readonly array $breedTraits = [],
        public readonly array $breedMeta = [],
        public readonly array $warnings = [],
        public readonly array $errors = [],
    ) {
    }

    /**
     * False whenever anything went wrong. The executor must refuse to write.
     *
     * Warnings alone do not block: a breed missing one trait value is worth
     * reporting but must not stop 256 good breeds reaching the site.
     */
    public function isSafeToApply(): bool
    {
        return $this->errors === []
            && $this->traits !== []
            && $this->questions !== []
            && $this->breedTraits !== [];
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $traitValues = 0;
        foreach ($this->breedTraits as $values) {
            $traitValues += count($values);
        }

        return [
            'traits'       => count($this->traits),
            'questions'    => count($this->questions),
            'links'        => count($this->links),
            'templates'    => count($this->templates),
            'ui_texts'     => count($this->uiTexts),
            'settings'     => count($this->settings),
            'breeds'       => count($this->breedTraits),
            'trait_values' => $traitValues,
        ];
    }
}
