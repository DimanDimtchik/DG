<?php
declare(strict_types=1);

/**
 * Passwortregeln für ELSTER-Zertifikatsdatei (.pfx) laut Mein ELSTER (Registrierungshilfe).
 *
 * Länge 10–128; Zeichen a–z A–Z 0–9 sowie zugelassene Sonderzeichen;
 * mindestens Buchstaben und Ziffern.
 */
final class ElsterCertificatePassword
{
    /** ELSTER-Sonderzeichen (ASCII, analog Hilfe / Passwortänderung-Zeichensatz). */
    public const SPECIAL_CHARS = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~ ';

    public const MIN_LENGTH = 10;
    public const MAX_LENGTH = 128;

    /**
     * @return list<string> Leere Liste = gültig.
     */
    public static function validate(string $password): array
    {
        $errors = [];
        $len = strlen($password);
        if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
            $errors[] = 'Das Zertifikat-Passwort muss zwischen '
                . self::MIN_LENGTH . ' und ' . self::MAX_LENGTH . ' Zeichen lang sein.';
        }

        $allowed = 'A-Za-z0-9' . preg_quote(self::SPECIAL_CHARS, '/');
        if ($password !== '' && !preg_match('/^[' . $allowed . ']+$/', $password)) {
            $errors[] = 'Ungültige Zeichen. Erlaubt: Buchstaben a–z/A–Z, Ziffern und ELSTER-Sonderzeichen '
                . '(u. a. ! " # $ % & \' ( ) * + , - . / : ; < = > ? @ [ \\ ] ^ _ ` { | } ~ und Leerzeichen).';
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Das Passwort muss Buchstaben und Ziffern enthalten (ELSTER-Empfehlung/Pflicht).';
        }

        return $errors;
    }

    public static function isValid(string $password): bool
    {
        return self::validate($password) === [];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $password): void
    {
        $errors = self::validate($password);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
    }
}
