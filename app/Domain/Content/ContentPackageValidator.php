<?php

namespace App\Domain\Content;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final class ContentPackageValidator
{
    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array<string, mixed>>  $artifacts
     *
     * @throws JsonException
     */
    public function validate(array $manifest, array $artifacts): ContentPackageValidationResult
    {
        $errors = [];
        $validatedArtifacts = [];

        foreach ($artifacts as $artifact) {
            $artifactKey = $this->requiredString($artifact, 'artifact_key');
            $pathReference = $this->requiredString($artifact, 'path_reference');
            $absolutePath = base_path($pathReference);
            $expectedChecksum = $artifact['checksum'] ?? null;
            $missing = ! File::isFile($absolutePath);
            $actualChecksum = null;

            if ($missing) {
                $errors[] = "Artifact [{$artifactKey}] is missing at [{$pathReference}].";
            } else {
                $actualChecksum = hash_file('sha256', $absolutePath);

                if (! is_string($actualChecksum)) {
                    throw new InvalidArgumentException("Artifact [{$artifactKey}] could not be hashed.");
                }

                if (is_string($expectedChecksum) && ! hash_equals(strtolower($expectedChecksum), strtolower($actualChecksum))) {
                    $errors[] = "Artifact [{$artifactKey}] checksum does not match.";
                }
            }

            $validatedArtifacts[] = [
                ...$artifact,
                'artifact_key' => $artifactKey,
                'path_reference' => $pathReference,
                'checksum_algorithm' => 'sha256',
                'checksum' => is_string($expectedChecksum) ? strtolower($expectedChecksum) : $actualChecksum,
                'actual_checksum' => $actualChecksum,
                'is_missing' => $missing,
            ];
        }

        return new ContentPackageValidationResult(
            valid: $errors === [],
            manifestHash: $this->hash($manifest),
            artifacts: $validatedArtifacts,
            errors: $errors,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     *
     * @throws JsonException
     */
    public function hash(array $value): string
    {
        $normalized = $this->normalize($value);
        $encoded = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $encoded);
    }

    /**
     * @param  array<string, mixed>  $artifact
     */
    private function requiredString(array $artifact, string $key): string
    {
        $value = $artifact[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Content artifact [{$key}] is required.");
        }

        return $value;
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
