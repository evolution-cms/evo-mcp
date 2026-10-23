<?php

declare(strict_types=1);

namespace Laravel\Mcp {
    final class Response
    {
        private function __construct(public readonly string $text, public readonly bool $isError)
        {
        }

        public static function error(string $text): self
        {
            return new self($text, true);
        }

        public function isError(): bool
        {
            return $this->isError;
        }
    }
}

namespace Illuminate\Validation {
    final class ValidationException extends \Exception
    {
        /**
         * @param  array<string, array<int, string>>  $errors
         */
        public function __construct(private array $errors)
        {
            parent::__construct('The given data was invalid.');
        }

        /**
         * @return array<string, array<int, string>>
         */
        public function errors(): array
        {
            return $this->errors;
        }
    }
}

namespace {
    use EvolutionCMS\eMCP\Support\ToolError;
    use Illuminate\Validation\ValidationException;

    require_once __DIR__ . '/../../src/Support/ToolError.php';

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function decode(\Laravel\Mcp\Response $response): array
    {
        $payload = json_decode($response->text, true);
        assertTrue(is_array($payload), 'the text block is one JSON object');

        return $payload;
    }

    $response = ToolError::make('not_found', 'Order [42] not found.', 404);
    assertTrue($response->isError(), 'result is marked isError');
    assertTrue(
        decode($response) === ['ok' => false, 'error' => 'not_found', 'message' => 'Order [42] not found.', 'status' => 404],
        'minimal shape'
    );

    assertTrue(decode(ToolError::make('refused', 'No.'))['status'] === 422, 'status defaults to 422');

    $payload = decode(ToolError::make('invalid', 'Bad input.', 422, [
        'errors' => ['status' => ['Unknown status.']],
        'hint' => 'Use orders_list to see statuses.',
        'ok' => true,
        'error' => 'overridden',
    ]));
    assertTrue($payload['errors'] === ['status' => ['Unknown status.']], 'errors are passed through');
    assertTrue($payload['hint'] === 'Use orders_list to see statuses.', 'extra keys are kept');
    assertTrue($payload['ok'] === false && $payload['error'] === 'invalid', 'extra keys cannot override the envelope');

    $many = [];
    for ($i = 1; $i <= 15; $i++) {
        $many[] = ['id' => $i, 'title' => "Order {$i}", 'email' => "buyer{$i}@example.com"];
    }
    $payload = decode(ToolError::make('ambiguous', 'Several orders match.', 409, ['candidates' => $many]));
    assertTrue(count($payload['candidates']) === ToolError::MAX_CANDIDATES, 'at most 10 candidates');
    assertTrue($payload['candidates'][0] === ['id' => 1, 'title' => 'Order 1'], 'candidates keep only id and title');
    assertTrue(($payload['candidates_truncated'] ?? false) === true, 'truncation is reported');

    $payload = decode(ToolError::make('ambiguous', 'Two match.', 409, ['candidates' => [
        ['id' => 'a', 'name' => 'Alpha'],
        (object)['id' => 7, 'title' => 'Seven'],
    ]]));
    assertTrue(
        $payload['candidates'] === [['id' => 'a', 'title' => 'Alpha'], ['id' => 7, 'title' => 'Seven']],
        'name counts as title; objects are accepted'
    );
    assertTrue(!isset($payload['candidates_truncated']), 'no truncation flag when everything fits');

    assertTrue(ToolError::isValidCode('aimage.quota_exceeded'), 'prefixed extension codes are allowed');
    assertTrue(!ToolError::isValidCode('quota_exceeded'), 'unprefixed unknown codes are not');
    $threw = false;
    try {
        ToolError::make('oops', 'x');
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    assertTrue($threw, 'an unknown code is a programming error');

    $payload = decode(ToolError::fromValidation(new ValidationException(['id' => ['Document [5] not found.']])));
    assertTrue($payload['error'] === 'invalid' && $payload['message'] === 'Document [5] not found.', 'ValidationException maps to invalid');
    assertTrue($payload['errors'] === ['id' => ['Document [5] not found.']], 'field errors are kept');

    echo "ToolError unit checks passed.\n";
}
