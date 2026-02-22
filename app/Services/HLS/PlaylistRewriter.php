<?php

namespace App\Services\HLS;

class PlaylistRewriter
{
    private const string URI_TAG_PATTERN = '/URI="([^"]+)"/';

    /**
     * @param  callable(string): string  $signReference
     */
    public function rewrite(string $playlistContent, callable $signReference): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $playlistContent);

        if (! is_array($lines)) {
            return $playlistContent;
        }
        $rewrittenLines = [];

        foreach ($lines as $line) {
            $rewrittenLines[] = $this->rewriteLine(
                line: $line,
                signReference: $signReference,
            );
        }
        return implode("\n", $rewrittenLines);
    }

    /**
     * @param  callable(string): string  $signReference
     */
    private function rewriteLine(string $line, callable $signReference): string
    {
        $line = $this->rewriteTaggedUris(
            line: $line,
            signReference: $signReference,
        );
        $trimmedLine = trim($line);

        if ($trimmedLine === '' || str_starts_with(ltrim($line), '#')) {
            return $line;
        }
        return $signReference($trimmedLine);
    }

    /**
     * @param  callable(string): string  $signReference
     */
    private function rewriteTaggedUris(string $line, callable $signReference): string
    {
        if (! str_contains($line, 'URI="')) {
            return $line;
        }
        $rewrittenLine = preg_replace_callback(
            self::URI_TAG_PATTERN,
            static function (array $matches) use ($signReference): string {
                $signedUri = $signReference((string) $matches[1]);

                return 'URI="'.$signedUri.'"';
            },
            $line,
        );
        return is_string($rewrittenLine) ? $rewrittenLine : $line;
    }
}
