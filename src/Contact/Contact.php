<?php
declare(strict_types=1);

/**
 * Contact.
 */
final class Contact
{
    /** @param list<array<string, string>> $bankAccounts */
    /** @param list<array<string, string>> $contactPersons */
    public function __construct(
        public readonly int $id,
        public readonly string $login,
        public readonly string $salutation,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $displayName,
        public readonly string $companyName,
        public readonly string $email,
        public readonly string $email2,
        public readonly string $phone1,
        public readonly string $phone2,
        public readonly string $customerNumber,
        public readonly string $supplierNumber,
        public readonly string $taxNumber,
        public readonly string $vatId,
        public readonly string $contactNote,
        public readonly string $address1Extra,
        public readonly string $address1Street,
        public readonly string $address1Postal,
        public readonly string $address1City,
        public readonly string $address1Country,
        public readonly string $address2Extra,
        public readonly string $address2Street,
        public readonly string $address2Postal,
        public readonly string $address2City,
        public readonly string $address2Country,
        public readonly string $website,
        public readonly string $contactRole,
        /** @var array<string, string> */
        public readonly array $social,
        public readonly array $bankAccounts,
        /** @var array<string, string> */
        public readonly array $employeeData,
        /** @var array<string, array<string, string>> */
        public readonly array $employeeFiles,
        public readonly array $contactPersons,
    ) {
    }

    /**
     * isCompany
     * @return bool
     */
    public function isCompany(): bool
    {
        return $this->salutation === 'Firma';
    }

    /**
     * roleLabel
     * @return string
     */
    public function roleLabel(): string
    {
        return CrmRole::label($this->contactRole);
    }

    /**
     * listLabel
     * @return string
     */
    public function listLabel(): string
    {
        if ($this->displayName !== '') {
            return $this->displayName;
        }

        if ($this->isCompany() && $this->companyName !== '') {
            return $this->companyName;
        }

        $name = trim($this->firstName . ' ' . $this->lastName);

        return $name !== '' ? $name : $this->login;
    }

    /**
     * addressLine1
     * @return string
     */
    public function addressLine1(): string
    {
        return self::formatAddress(
            $this->address1Extra,
            $this->address1Street,
            $this->address1Postal,
            $this->address1City,
            $this->address1Country,
        );
    }

    /**
     * addressLine2
     * @return string
     */
    public function addressLine2(): string
    {
        return self::formatAddress(
            $this->address2Extra,
            $this->address2Street,
            $this->address2Postal,
            $this->address2City,
            $this->address2Country,
        );
    }

    /**
     * socialLinks.
     *
     * @return list<array{label: string, url: string}>
     */
        public function socialLinks(): array
    {
        $labels = [
            'linkedin' => 'LinkedIn',
            'xing' => 'Xing',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'x' => 'X (Twitter)',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'github' => 'GitHub',
        ];
        $links = [];
        foreach ($labels as $key => $label) {
            $url = trim($this->social[$key] ?? '');
            if ($url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * formatAddress
     * @param string $extra
     * @param string $street
     * @param string $postal
     * @param string $city
     * @param string $country
     * @return string
     */
    private static function formatAddress(
        string $extra,
        string $street,
        string $postal,
        string $city,
        string $country,
    ): string {
        $parts = array_filter([
            $street,
            $extra,
            trim($postal . ' ' . $city),
            $country !== '' && $country !== 'DE' ? $country : '',
        ]);

        return implode(', ', $parts);
    }
}
