<?php
declare(strict_types=1);

final class AuctionWatchersValidator
{
    private array $errors = [];

    public function validate(array $data): bool
    {
        $this->errors = [];

        foreach (['auction_id', 'user_id'] as $field) {
            if (empty($data[$field]) || !is_numeric($data[$field])) {
                $this->errors[] = "Field '{$field}' is required and must be numeric";
            }
        }

        foreach (['notify_before_end', 'notify_on_outbid', 'notify_on_winner'] as $flag) {
            if (isset($data[$flag]) && !in_array((int)$data[$flag], [0, 1], true)) {
                $this->errors[] = "{$flag} must be 0 or 1";
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
