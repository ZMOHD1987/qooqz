<?php
declare(strict_types=1);

final class BadWordsService
{
    private PdoBadWordsRepository $repo;

    /** Characters commonly used to disguise words (mapped to their real letters) */
    private const CHAR_MAP = [
        '@' => 'a', '$' => 's', '0' => 'o', '1' => 'i', '3' => 'e',
        '!' => 'i', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b',
    ];

    /** Arabic diacritics (tashkeel) to strip */
    private const ARABIC_DIACRITICS = [
        "\xD9\x8B", "\xD9\x8C", "\xD9\x8D", "\xD9\x8E", "\xD9\x8F",
        "\xD9\x90", "\xD9\x91", "\xD9\x92",
    ];

    public function __construct(PdoBadWordsRepository $repo)
    {
        $this->repo = $repo;
    }

    // ================================
    // CRUD
    // ================================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => ($limit !== null && $limit > 0) ? (int)ceil($total / $limit) : 0,
            ],
        ];
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function create(array $data): int
    {
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("Bad word not found with ID: $id");
        }
        return $this->repo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException("Bad word not found with ID: $id");
        }
        return $this->repo->delete($id);
    }

    // ================================
    // Translations
    // ================================
    public function getTranslations(int $badWordId): array
    {
        return $this->repo->getTranslations($badWordId);
    }

    public function saveTranslation(array $data): int
    {
        return $this->repo->saveTranslation($data);
    }

    public function deleteTranslation(int $id): bool
    {
        return $this->repo->deleteTranslation($id);
    }

    // ================================
    // Text checking / filtering
    // ================================

    /**
     * Check text for bad words.
     *
     * @param string      $text         The text to check
     * @param string|null $languageCode Optional language code to limit checks
     * @return array{clean: bool, found: array<array{word: string, severity: string, position: int}>}
     */
    public function checkText(string $text, ?string $languageCode = null): array
    {
        if (trim($text) === '') {
            return ['clean' => true, 'found' => []];
        }

        $allWords = $this->repo->getAllActiveWords($languageCode);
        $found    = [];

        // Normalize input text for matching
        $normalizedText = $this->normalizeText($text);

        // Check base words
        foreach ($allWords['words'] as $entry) {
            $matches = $this->matchWord($normalizedText, $entry['word'], (bool)$entry['is_regex']);
            foreach ($matches as $pos) {
                $found[] = [
                    'word'     => $entry['word'],
                    'severity' => $entry['severity'],
                    'position' => $pos,
                ];
            }
        }

        // Check translated words
        foreach ($allWords['translations'] as $entry) {
            $matches = $this->matchWord($normalizedText, $entry['word'], (bool)$entry['is_regex']);
            foreach ($matches as $pos) {
                $found[] = [
                    'word'     => $entry['word'],
                    'severity' => $entry['severity'],
                    'position' => $pos,
                ];
            }
        }

        return [
            'clean' => empty($found),
            'found' => $found,
        ];
    }

    /**
     * Filter (censor) bad words in text by replacing them with asterisks.
     */
    public function filterText(string $text, ?string $languageCode = null): string
    {
        if (trim($text) === '') return $text;

        $allWords = $this->repo->getAllActiveWords($languageCode);
        $result   = $text;

        // Collect all bad words
        $words = [];
        foreach ($allWords['words'] as $entry) {
            if (!(bool)$entry['is_regex']) {
                $words[] = $entry['word'];
            }
        }
        foreach ($allWords['translations'] as $entry) {
            if (!(bool)$entry['is_regex']) {
                $words[] = $entry['word'];
            }
        }

        // Sort by length (longest first) to avoid partial replacements
        usort($words, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($words as $word) {
            $pattern = $this->buildFlexiblePattern($word);
            $result  = preg_replace_callback($pattern, fn($m) => str_repeat('*', mb_strlen($m[0])), $result) ?? $result;
        }

        // Handle regex patterns
        foreach (array_merge($allWords['words'], $allWords['translations']) as $entry) {
            if ((bool)$entry['is_regex']) {
                $result = preg_replace('/' . $entry['word'] . '/iu', '***', $result) ?? $result;
            }
        }

        return $result;
    }

    // ================================
    // Private helpers
    // ================================

    /**
     * Normalize text: lowercase, strip diacritics, replace look-alike chars
     */
    private function normalizeText(string $text): string
    {
        // Strip Arabic diacritics (tashkeel)
        $text = str_replace(self::ARABIC_DIACRITICS, '', $text);

        // Lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace look-alike characters
        $text = strtr($text, self::CHAR_MAP);

        return $text;
    }

    /**
     * Match a bad word in normalized text.
     * Handles separated letters: "b a d" or "b.a.d" or "b-a-d"
     *
     * @return int[] Array of match positions
     */
    private function matchWord(string $normalizedText, string $word, bool $isRegex): array
    {
        $positions = [];

        if ($isRegex) {
            if (@preg_match_all('/' . $word . '/iu', $normalizedText, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $positions[] = $match[1];
                }
            }
            return $positions;
        }

        $normalizedWord = $this->normalizeText($word);

        // Direct match
        $pos = 0;
        while (($found = mb_strpos($normalizedText, $normalizedWord, $pos)) !== false) {
            $positions[] = $found;
            $pos = $found + 1;
        }

        // Separated match: build pattern allowing separators between each char
        $pattern = $this->buildFlexiblePattern($normalizedWord);
        if (@preg_match_all($pattern, $normalizedText, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                if (!in_array($match[1], $positions, true)) {
                    $positions[] = $match[1];
                }
            }
        }

        return $positions;
    }

    /**
     * Build a regex that matches a word even with separators between letters.
     * e.g. "bad" → /b[\s.\-_*]*a[\s.\-_*]*d/iu
     */
    private function buildFlexiblePattern(string $word): string
    {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($chars)) return '/(?!)/';

        $escaped = array_map(fn($ch) => preg_quote($ch, '/'), $chars);
        $sep     = '[\s.\-_*~`\'",;:!?^#|\/\\\\]*';

        return '/' . implode($sep, $escaped) . '/iu';
    }
}
