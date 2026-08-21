<?php

declare(strict_types=1);

/**
 * CRM login / account password rules.
 *
 * Special characters: most printable ASCII punctuation allowed.
 * The shorter list is the disallowed set → shown in UI hints.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 30;

    /** Shorter list → hint text for users. */
    public const DISALLOWED_SPECIALS = " \"'\\<>`";

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $password, string $confirm = ''): void
    {
        if ($confirm !== '' && $password !== $confirm) {
            throw new InvalidArgumentException('Passwörter stimmen nicht überein.');
        }
        $errors = self::errors($password);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
    }

    /** @return list<string> */
    public static function errors(string $password): array
    {
        $errors = [];
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Passwort mindestens ' . self::MIN_LENGTH . ' Zeichen.';
        }
        if (!preg_match('/[a-z]/u', $password)) {
            $errors[] = 'Mindestens ein Kleinbuchstabe erforderlich.';
        }
        if (!preg_match('/[A-Z]/u', $password)) {
            $errors[] = 'Mindestens ein Großbuchstabe erforderlich.';
        }
        if (!preg_match('/[0-9]/u', $password)) {
            $errors[] = 'Mindestens eine Ziffer erforderlich.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/u', $password)) {
            $errors[] = 'Mindestens ein Sonderzeichen erforderlich.';
        }
        $disallowed = self::DISALLOWED_SPECIALS;
        $len = strlen($password);
        for ($i = 0; $i < $len; $i++) {
            $ch = $password[$i];
            if (str_contains($disallowed, $ch)) {
                $errors[] = 'Unzulässiges Zeichen. ' . self::hintShort();
                break;
            }
            $ord = ord($ch);
            if ($ord < 33 || $ord > 126) {
                $errors[] = 'Nur druckbare ASCII-Zeichen erlaubt (keine Umlaute/Leerzeichen/Emoji).';
                break;
            }
        }

        return array_values(array_unique($errors));
    }

    public static function isValid(string $password): bool
    {
        return self::errors($password) === [];
    }

    /** Full hint for forms. */
    public static function hint(): string
    {
        return 'Mindestens ' . self::MIN_LENGTH
            . ' Zeichen, mit Groß- und Kleinbuchstaben, Ziffer und Sonderzeichen. '
            . self::hintShort()
            . ' Tipp: Passwort vom Browser vorschlagen lassen (Schlüssel-Symbol im Feld).';
    }

    public static function hintShort(): string
    {
        // Disallowed list is shorter than an allow-list of ~25 punctuation marks.
        return 'Nicht erlaubt: Leerzeichen sowie " \' \\ < > `';
    }
}
