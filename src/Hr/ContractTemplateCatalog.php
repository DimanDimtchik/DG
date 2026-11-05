<?php
declare(strict_types=1);

/**
 * Kern-Katalog der Vertragsbausteine (Platzhalter {{…}}) — tenant-neutral.
 * Quelle für Seed und Fallback-PDF bis Editor (V1+) greift.
 */
final class ContractTemplateCatalog
{
    public const CODE_FULLTIME = 'employment_fulltime';
    public const CODE_PARTTIME = 'employment_parttime';
    public const CODE_MINIJOB = 'employment_minijob';
    public const CODE_WARNING = 'hr_warning';

    /**
     * @return array<string, array{title: string, contract_type?: string, event_type?: string}>
     */
    public static function templates(): array
    {
        return [
            self::CODE_FULLTIME => [
                'title' => 'Arbeitsvertrag Vollzeit',
                'contract_type' => EmploymentContractContext::TYPE_FULLTIME,
            ],
            self::CODE_PARTTIME => [
                'title' => 'Arbeitsvertrag Teilzeit',
                'contract_type' => EmploymentContractContext::TYPE_PARTTIME,
            ],
            self::CODE_MINIJOB => [
                'title' => 'Arbeitsvertrag Minijob',
                'contract_type' => EmploymentContractContext::TYPE_MINIJOB,
            ],
            self::CODE_WARNING => [
                'title' => 'Abmahnung',
                'event_type' => HrPersonnelEventRepository::TYPE_WARNING,
            ],
        ];
    }

    public static function codeForContractType(string $type): string
    {
        return match ($type) {
            EmploymentContractContext::TYPE_MINIJOB => self::CODE_MINIJOB,
            EmploymentContractContext::TYPE_PARTTIME => self::CODE_PARTTIME,
            default => self::CODE_FULLTIME,
        };
    }

    public static function codeForEventType(string $eventType): ?string
    {
        return match ($eventType) {
            HrPersonnelEventRepository::TYPE_WARNING => self::CODE_WARNING,
            default => null,
        };
    }

    public static function isHrEventCode(string $code): bool
    {
        return $code === self::CODE_WARNING;
    }

    /**
     * @return list<array{block_key: string, title: string, body_html: string, is_annex: int, enabled: int, sort_order?: int}>
     */
    public static function blocksForCode(string $code): array
    {
        $meta = self::templates()[$code] ?? null;
        if ($meta === null) {
            return [];
        }
        if ($code === self::CODE_WARNING) {
            return self::warningBlocks();
        }
        $type = (string) ($meta['contract_type'] ?? EmploymentContractContext::TYPE_FULLTIME);
        $blocks = [];
        $n = 0;
        $add = static function (
            string $key,
            string $title,
            string $body,
            bool $annex = false
        ) use (&$blocks, &$n): void {
            $blocks[] = [
                'block_key' => $key,
                'title' => $title,
                'body_html' => $body,
                'is_annex' => $annex ? 1 : 0,
                'enabled' => 1,
                'sort_order' => $n++,
            ];
        };

        $add('intro', 'Hinweis Nachweisgesetz', '<p>{{disclaimer}}</p>');
        $add(
            'parties',
            'Vertragsparteien',
            '<p><strong>Zwischen</strong><br>{{employer_name}}<br>{{employer_address_html}}'
            . '<br><br><strong>und</strong><br>{{employee_name}}{{employee_birth_line}}'
            . '<br>{{employee_address_html}}'
            . '<br><br>wird folgender Arbeitsvertrag geschlossen:</p>'
        );
        $add(
            's1_start',
            'Beginn und Art des Arbeitsverhältnisses',
            '<p><strong>§ 1 Beginn und Art des Arbeitsverhältnisses</strong></p>'
            . '<p>{{clause_contract_start}}</p>'
            . '<p>{{clause_duration}}</p>'
            . '<p>{{clause_probation}}</p>'
            . '<p>{{clause_contract_type}}</p>'
            . ($type === EmploymentContractContext::TYPE_MINIJOB ? '<p>{{clause_minijob_sgb}}</p>' : '')
        );
        $add(
            's2_job',
            'Tätigkeit und Arbeitsort',
            '<p><strong>§ 2 Tätigkeit und Arbeitsort</strong></p>'
            . '<p>{{clause_activity}}</p>'
            . '<p>{{clause_work_location}}</p>'
            . '<p>{{clause_employer_site}}</p>'
            . '<p>{{clause_directives}}</p>'
        );
        $add(
            's3_hours',
            'Arbeitszeit',
            '<p><strong>§ 3 Arbeitszeit</strong></p>'
            . '<p>{{clause_working_hours}}</p>'
            . '<p>{{clause_daily_minutes}}</p>'
            . ($type === EmploymentContractContext::TYPE_MINIJOB ? '<p>{{clause_minijob_hours}}</p>' : '')
            . '<p>{{clause_hours_law}}</p>'
        );
        $add(
            's4_pay',
            'Vergütung',
            '<p><strong>§ 4 Vergütung</strong></p>'
            . '<p>{{clause_salary}}</p>'
            . '<p>{{clause_min_wage}}</p>'
            . '<p>{{clause_pay_due}}</p>'
            . ($type === EmploymentContractContext::TYPE_MINIJOB ? '<p>{{clause_minijob_pay_hint}}</p>' : '')
        );
        $add(
            's5_vacation',
            'Urlaub',
            '<p><strong>§ 5 Urlaub</strong></p>'
            . '<p>{{clause_vacation}}</p>'
            . '<p>{{clause_vacation_law}}</p>'
        );
        $add(
            's6_sickness',
            'Krankheit / Arbeitsunfähigkeit',
            '<p><strong>§ 6 Krankheit / Arbeitsunfähigkeit</strong></p>'
            . '<p>{{clause_sickness_notify}}</p>'
            . '<p>{{clause_sickness_contact}}</p>'
            . '<p>{{clause_sickness_efzg}}</p>'
        );
        $add(
            's7_privacy',
            'Datenschutz und Verschwiegenheit',
            '<p><strong>§ 7 Datenschutz und Verschwiegenheit</strong></p>'
            . '<p>{{clause_privacy_dsgvo}}</p>'
            . '<p>{{clause_privacy_confidential}}</p>'
        );
        $add(
            's8_minor',
            'Besonderheiten bei Minderjährigen (JArbSchG)',
            '<p><strong>§ 8 Besonderheiten bei Minderjährigen (JArbSchG)</strong></p>'
            . '<p>{{clause_minor_jarbschg}}</p>'
            . '<p>{{clause_minor_medical}}</p>'
            . '<p>{{clause_minor_guardian}}</p>'
            . '<p>{{clause_minor_consent}}</p>'
        );
        $add(
            'signatures',
            'Unterschriften',
            '<p><strong>Unterschriften</strong></p>'
            . '<p>{{clause_sig_place}}</p>'
            . '<p>{{clause_sig_employer}}</p>'
            . '<p>{{clause_sig_employee}}</p>'
            . '<p>{{clause_sig_guardian}}</p>'
        );
        $add(
            'annex_training',
            'Anlage: Kurzschulung / Unterweisung',
            '<p><strong>Anlage: Kurzschulung / Unterweisung</strong></p>'
            . '<p>{{clause_annex_training_intro}}</p>'
            . '{{clause_annex_training_list_html}}'
            . '<p>{{clause_annex_training_video}}</p>'
            . '<p>{{clause_annex_training_confirm}}</p>'
            . '<p>{{clause_annex_training_employee}}</p>'
            . '<p>{{clause_annex_training_guardian}}</p>',
            true
        );
        if ($type === EmploymentContractContext::TYPE_MINIJOB) {
            $add(
                'annex_pension',
                'Anlage: Rentenversicherungsbefreiung (Minijob)',
                '<p><strong>Anlage: Rentenversicherungsbefreiung (Minijob)</strong></p>'
                . '<p>{{clause_annex_pension_text}}</p>'
                . '<p>{{clause_annex_pension_employee}}</p>'
                . '<p>{{clause_annex_pension_stamp}}</p>'
                . '<p>{{clause_annex_pension_sig}}</p>'
                . '<p>{{clause_annex_pension_guardian}}</p>',
                true
            );
        }
        $add(
            'annex_ifsg',
            'Anlage: Infektionsschutz / Lebensmittelkontakt',
            '<p><strong>Anlage: Infektionsschutz / Lebensmittelkontakt (nur bei tatsächlichem Kontakt)</strong></p>'
            . '<p>{{clause_annex_ifsg_text}}</p>'
            . '<p>{{clause_annex_ifsg_note}}</p>'
            . '<p>{{clause_annex_ifsg_confirm}}</p>',
            true
        );

        return $blocks;
    }

    /**
     * @return list<array{block_key: string, title: string, body_html: string, is_annex: int, enabled: int, sort_order: int}>
     */
    private static function warningBlocks(): array
    {
        $blocks = [];
        $n = 0;
        $add = static function (string $key, string $title, string $body) use (&$blocks, &$n): void {
            $blocks[] = [
                'block_key' => $key,
                'title' => $title,
                'body_html' => $body,
                'is_annex' => 0,
                'enabled' => 1,
                'sort_order' => $n++,
            ];
        };
        $add(
            'hr_disclaimer',
            'Hinweis',
            '<p>{{hr_disclaimer}}</p>'
        );
        $add(
            'hr_warning_intro',
            'Einleitung',
            '<p>{{hr_warning_intro}}</p>'
        );
        $add(
            'hr_facts',
            'Sachverhalt',
            '<p><strong>Sachverhalt</strong></p><p>{{facts_html}}</p>'
        );
        $add(
            'hr_expectation',
            'Erwartung',
            '<p><strong>Erwartung</strong></p><p>{{expectation_html}}</p>'
        );
        $add(
            'hr_threat',
            'Androhung',
            '<p><strong>Androhung</strong></p><p>{{threat_html}}</p>'
        );
        $add(
            'hr_employer_footer',
            'Arbeitgeber',
            '<p>{{hr_employer_footer_html}}</p>'
        );

        return $blocks;
    }
}
