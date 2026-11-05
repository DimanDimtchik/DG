<?php
declare(strict_types=1);

/**
 * Anreichert german-health-insurers.json um website + appointment_url.
 * Einmalig lokal ausführen; Ergebnis committen.
 */

$path = dirname(__DIR__) . '/assets/data/german-health-insurers.json';
$rows = json_decode((string) file_get_contents($path), true);
if (!is_array($rows)) {
    fwrite(STDERR, "JSON unlesbar\n");
    exit(1);
}

/** @var array<string, array{website: string, appointment_url?: string}> */
$map = [
    'Techniker Krankenkasse' => [
        'website' => 'https://www.tk.de',
        'appointment_url' => 'https://www.tk.de/techniker/krankheit-und-behandlungen/praxisbesuch-und-klinikaufenthalt/tk-terminservice-2009696',
    ],
    'Barmer' => [
        'website' => 'https://www.barmer.de',
        'appointment_url' => 'https://www.barmer.de/unsere-leistungen/leistungen-a-z/beratung-und-hilfen/termine-beim-facharzt-1055498',
    ],
    'DAK-Gesundheit' => [
        'website' => 'https://www.dak.de',
        'appointment_url' => 'https://www.dak.de/dak/leistungen/dak-arzttermin-hotline_12336',
    ],
    'AOK Bayern' => [
        'website' => 'https://www.aok.de/pk/bayern/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Baden-Württemberg' => [
        'website' => 'https://www.aok.de/pk/bw/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Bremen/Bremerhaven' => [
        'website' => 'https://www.aok.de/pk/bremen/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Hessen' => [
        'website' => 'https://www.aok.de/pk/hessen/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Niedersachsen' => [
        'website' => 'https://www.aok.de/pk/niedersachsen/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Nordost' => [
        'website' => 'https://www.aok.de/pk/nordost/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK NordWest' => [
        'website' => 'https://www.aok.de/pk/nordwest/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK PLUS' => [
        'website' => 'https://www.aok.de/pk/plus/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Rheinland/Hamburg' => [
        'website' => 'https://www.aok.de/pk/rh/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Rheinland-Pfalz/Saarland' => [
        'website' => 'https://www.aok.de/pk/rps/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Sachsen-Anhalt' => [
        'website' => 'https://www.aok.de/pk/sachsen-anhalt/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Sachsen' => [
        'website' => 'https://www.aok.de/pk/plus/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'AOK Thüringen' => [
        'website' => 'https://www.aok.de/pk/nordost/',
        'appointment_url' => 'https://www.aok.de/pk/leistungen/medizinische-behandlung/facharztterminservice/',
    ],
    'hkk Krankenkasse' => [
        'website' => 'https://www.hkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Handelskrankenkasse (hkk)' => [
        'website' => 'https://www.hkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'KKH - Kaufmännische Krankenkasse' => [
        'website' => 'https://www.kkh.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'HEK - Hanseatische Krankenkasse' => [
        'website' => 'https://www.hek.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'IKK classic' => [
        'website' => 'https://www.ikk-classic.de',
        'appointment_url' => 'https://terminservice.4sigma.de/ikk-classic/index.php',
    ],
    'IKK gesund plus' => [
        'website' => 'https://www.ikk-gesundplus.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'IKK Südwest' => [
        'website' => 'https://www.ikk-suedwest.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK Mobil Oil' => [
        'website' => 'https://www.bkk-mobil-oil.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'mobil krankenkasse' => [
        'website' => 'https://www.mobil-krankenkasse.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK VBU' => [
        'website' => 'https://www.bkk-vbu.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK firmus' => [
        'website' => 'https://www.bkk-firmus.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK GILDEMEISTER SEIDENSTICKER' => [
        'website' => 'https://www.bkkgs.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK Pfalz' => [
        'website' => 'https://www.bkkpfalz.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK ProVita' => [
        'website' => 'https://www.bkk-provita.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK Scheufelen' => [
        'website' => 'https://www.bkk-scheufelen.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK VerbundPlus' => [
        'website' => 'https://www.bkk-verbundplus.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK Wirtschaft & Finanzen' => [
        'website' => 'https://www.bkk-wf.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BKK ZF & Partner' => [
        'website' => 'https://www.bkk-zf-partner.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'BMW BKK' => [
        'website' => 'https://www.bmw-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Bosch BKK' => [
        'website' => 'https://www.bosch-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Continentale Betriebskrankenkasse' => [
        'website' => 'https://www.continentale-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Daimler BKK' => [
        'website' => 'https://www.daimler-bkk.com',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Deutsche Rentenversicherung Knappschaft-Bahn-See' => [
        'website' => 'https://www.kbs.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Knappschaft' => [
        'website' => 'https://www.kbs.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'energie-BKK' => [
        'website' => 'https://www.energie-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'mhplus Betriebskrankenkasse' => [
        'website' => 'https://www.mhplus.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'pronova BKK' => [
        'website' => 'https://www.pronovabkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'R+V Betriebskrankenkasse' => [
        'website' => 'https://www.ruv-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Salus BKK' => [
        'website' => 'https://www.salus-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'SBK Siemens-Betriebskrankenkasse' => [
        'website' => 'https://www.sbk.org',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Securvita BKK' => [
        'website' => 'https://www.securvita.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'SKD BKK' => [
        'website' => 'https://www.skd-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Viactiv Krankenkasse' => [
        'website' => 'https://www.viactiv.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'vivida bkk' => [
        'website' => 'https://www.vivida-bkk.de',
        'appointment_url' => 'https://www.116117-termine.de',
    ],
    'Allianz Private Krankenversicherungs-AG' => ['website' => 'https://www.allianz.de/gesundheit/krankenversicherung/'],
    'AXA Krankenversicherung AG' => ['website' => 'https://www.axa.de/krankenversicherung'],
    'ARAG Krankenversicherungs-AG' => ['website' => 'https://www.arag.de/krankenversicherung/'],
    'Barmenia Krankenversicherung AG' => ['website' => 'https://www.barmenia.de'],
    'Central Krankenversicherung AG' => ['website' => 'https://www.central.de'],
    'Continentale Krankenversicherung a.G.' => ['website' => 'https://www.continentale.de'],
    'Debeka Krankenversicherungsverein a. G.' => ['website' => 'https://www.debeka.de'],
    'DEVK Krankenversicherungs-AG' => ['website' => 'https://www.devk.de'],
    'DKV Deutsche Krankenversicherung AG' => ['website' => 'https://www.dkv.com'],
    'ENVIVAS Krankenversicherung AG' => ['website' => 'https://www.envivas.de'],
    'ERGO Krankenversicherung AG' => ['website' => 'https://www.ergo.de'],
    'Gothaer Krankenversicherung AG' => ['website' => 'https://www.gothaer.de'],
    'Hallesche Krankenversicherung a.G.' => ['website' => 'https://www.hallesche.de'],
    'HanseMerkur Krankenversicherung AG' => ['website' => 'https://www.hansemerkur.de'],
    'HUK-COBURG-Krankenversicherung AG' => ['website' => 'https://www.huk.de'],
    'Inter Krankenversicherung AG' => ['website' => 'https://www.inter.de'],
    'LKH Landeskrankenhilfe V.a.G.' => ['website' => 'https://www.lkh.de'],
    'LVM Krankenversicherungs-AG' => ['website' => 'https://www.lvm.de'],
    'Mecklenburgische Krankenversicherungs-AG' => ['website' => 'https://www.mecklenburgische.de'],
    'Münchener Verein Krankenversicherung a.G.' => ['website' => 'https://www.muenchener-verein.de'],
    'NÜRNBERGER Krankenversicherung AG' => ['website' => 'https://www.nuernberger.de'],
    'R+V Krankenversicherung AG' => ['website' => 'https://www.ruv.de'],
    'Signal Iduna Krankenversicherung a.G.' => ['website' => 'https://www.signal-iduna.de'],
    'SDK Süddeutsche Krankenversicherung a.G.' => ['website' => 'https://www.sdk.de'],
    'Süddeutsche Krankenversicherung a.G.' => ['website' => 'https://www.sdk.de'],
    'Universa Krankenversicherung a.G.' => ['website' => 'https://www.universa.de'],
    'Württembergische Krankenversicherung AG' => ['website' => 'https://www.wuerttembergische.de'],
    'DBV Deutsche Beamtenversicherung AG' => ['website' => 'https://www.dbv.de'],
    'Concordia Krankenversicherungs-AG' => ['website' => 'https://www.concordia.de'],
    'PAX-Familienfürsorge Krankenversicherung AG' => ['website' => 'https://www.pax.de'],
    'Union Krankenversicherung AG' => ['website' => 'https://www.ukv.de'],
    'Victoria Krankenversicherung AG' => ['website' => 'https://www.ergo.de'],
];

$gkvDefaultAppointment = 'https://www.116117-termine.de';
$out = [];
$missing = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $name = (string) ($row['name'] ?? '');
    $type = (string) ($row['type'] ?? 'gkv');
    $extra = $map[$name] ?? null;
    if ($extra === null) {
        $missing[] = $name;
        $extra = ['website' => ''];
    }
    $entry = [
        'name' => $name,
        'type' => $type,
    ];
    if (!empty($row['code'])) {
        $entry['code'] = (string) $row['code'];
    }
    $entry['website'] = (string) ($extra['website'] ?? '');
    $appt = trim((string) ($extra['appointment_url'] ?? ''));
    if ($appt === '' && $type === 'gkv') {
        $appt = $gkvDefaultAppointment;
    }
    $entry['appointment_url'] = $appt;
    $out[] = $entry;
}

file_put_contents(
    $path,
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n"
);

echo 'enriched=' . count($out) . ' missing_map=' . count($missing) . PHP_EOL;
foreach ($missing as $n) {
    echo "MISSING: $n\n";
}
