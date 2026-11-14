<?php
namespace App\Service\Terminal;

/**
 * ArgsParser
 *
 * Small helper to split a terminal command into:
 *  - positional arguments (args)
 *  - named flags (options starting with "--")
 *
 * Example:
 *   input tokens: ["users:add", "--emails=HestTest@example.com", "--roles=ROLE_ADMIN,ROLE_USER", "123"]
 *   parse result:
 *     [
 *       'args'  => ["123"],   // plain positional values
 *       'flags' => [          // options with "--"
 *          'emails' => "alice@example.com",
 *          'roles' => "ROLE_ADMIN,ROLE_USER"
 *       ]
 *     ]
 */
final class ArgsParser
{
    /**
     * Parse an array of tokens into args + flags.
     *
     * @param array<int,string> $tokens Tokens from the input (already split on whitespace).
     *
     * @return array{
     *   args:  array<int,string>,              // positional arguments
     *   flags: array<string,string|bool>       // flags (--key=value or --flag)
     * }
     */
    public static function parse(array $tokens): array
    {
        $arguments  = [];  // Positional arguments, e.g. "123"
        $flags = [];  // Named flags, e.g. ["emails" => "alice@example.com"]

        foreach ($tokens as $token) {
            // If it starts with "--", treat it as a flag
            if (str_starts_with($token, '--')) {
                $token = substr($token, 2); // remove leading "--"

                // Case: --key=value
                if (str_contains($token, '=')) {
                    [$flagName, $flagValue] = explode('=', $token, 2);
                    $flags[$flagName] = $flagValue;
                }
                // Case: --flag (no value)
                else {
                    $flags[$token] = true;
                }
            }
            // Otherwise it’s a positional arg
            else {
                $arguments[] = $token;
            }
        }

        return ['args' => $arguments, 'flags' => $flags];
    }
}
