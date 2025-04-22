<?php

require_once "FszNotenVerwaltung.class.php";
require_once "lib/classes/Seminar.class.php";

require_once "vendor/write_excel/OLEwriter.php";
require_once "vendor/write_excel/BIFFwriter.php";
require_once "vendor/write_excel/Worksheet.php";
require_once "vendor/write_excel/Workbook.php";

define('MATRIKELNUMMER_ID', 'ec4d5347bd30ade7dd23165ba9ebd446');
define('SWS_ID', 'e5d95786510257dd4abb4b46cbd804a3');

class FszNotenVerwaltungExcelExporter
{
    private array $sprachen = [
        'DE' => 'Deutsch',
        'EN' => 'Englisch',
        'FR' => 'Französisch',
        'SP' => 'Spanisch',
        'IT' => 'Italienisch',
        'JA' => 'Japanisch',
        'RU' => 'Russisch',
        'PO' => 'Portugisisch',
        'LT' => 'Latein',
        'CH' => 'Chinesisch',
        'SW' => 'Schwedisch',
        'GA' => 'Altgriechisch',
        'GN' => 'Neugriechisch',
        'PL' => 'Polnisch',
        'TR' => 'Türkisch'
    ];

    private Workbook $workbook;
    private array $to_export = [];
    private Worksheet $worksheet;
    private mixed $caption_format;
    private mixed $data_format;
    private int $row = 0;

    public function __construct(array $to_export = [])
    {
        $this->to_export = $to_export;
    }

    public function addExportSeminar(string $id): void
    {
        if (!in_array($id, $this->to_export, true)) {
            $this->to_export[] = $id;
        }
    }

    public function createExcelSheet(array $user_ids = []): string
    {
        $tmpfile = $GLOBALS['TMP_PATH'] . '/' . md5(uniqid('write_excel', true));
        $this->workbook = new Workbook($tmpfile);

        // Set up formats
        $this->caption_format = $this->workbook->addformat();
        $this->caption_format->set_size(10);
        $this->caption_format->set_align("left");
        $this->caption_format->set_align("vcenter");
        $this->caption_format->set_bold();

        $this->data_format = $this->workbook->addformat();
        $this->data_format->set_size(10);
        $this->data_format->set_align("left");
        $this->data_format->set_align("vcenter");

        // Create the first worksheet
        $this->worksheet = $this->workbook->addworksheet("Notenverwaltung");
        $this->initializeWorksheetHeader();

        foreach ($this->to_export as $id) {
            $this->exportOneSeminar($id, $user_ids);
        }

        $this->workbook->close();
        return $tmpfile;
    }

    private function initializeWorksheetHeader(): void
    {
        $headers = [
            "Nachname", "Vorname", "Email", "MatriNr", "Niveau",
            "NoteZahl", "NoteWort", "ECTS", "Schein", "Leistung",
            "BelegNr", "Kurs", "SWS", "DozName", "DozVorname",
            "Semester", "Sprache", "Geändert am", "Geändert von"
        ];

        $fsz = new FszNotenVerwaltung($this->to_export[0]);
        foreach (array_keys($fsz->unicert_marks) as $ucert) {
            $headers[] = utf8_decode($ucert);
        }

        $col = 0;
        foreach ($headers as $header) {
            $this->worksheet->write_string($this->row, $col++, utf8_decode($header), $this->caption_format);
        }
        $this->row++;
    }

    public function exportOneSeminar(string $id, array $user_ids = []): void
    {
        $fsz = new FszNotenVerwaltung($id);
        $fsz->fsz = true;

        if (!($fsz->num_schein || $fsz->num_grades)) {
            return;
        }

        $seminar = new Seminar($fsz->seminar_id);
        $semester = SemesterData::GetInstance();
        $one_semester = $semester->getSemesterDataByDate($seminar->getSemesterStartTime());
        $dozent = current($seminar->getMembers('dozent'));
        $sws = $this->getDatafieldValue(SWS_ID, $fsz->seminar_id);

        $kurs = trim(strstr($seminar->getName(), ' '));
        $belegnr = trim(strstr($seminar->getName(), ' ', true));
        $sprache = $this->sprachen[substr($belegnr, 0, 2)] ?? 'Unbekannt';

        preg_match_all("/\(([^\)]*)\)/", $kurs, $matches);
        foreach ($fsz->members as $user_id => $user_data) {
            if (!empty($user_ids) && !in_array($user_id, $user_ids, true)) {
                continue;
            }

            $final = $fsz->getFinalGrade($user_id);
            if (!$final && !$fsz->getValueSchein($user_id) && !$fsz->getValueErfolg($user_id)) {
                continue;
            }

            $this->exportUserRow($fsz, $user_data, $seminar, $one_semester, $dozent, $sws, $belegnr, $kurs, $sprache, $matches, $user_id, $final);
        }
    }

    private function exportUserRow(
        FszNotenVerwaltung $fsz,
        array $user_data,
        Seminar $seminar,
        array $one_semester,
        array $dozent,
        string $sws,
        string $belegnr,
        string $kurs,
        string $sprache,
        array $matches,
        string $user_id,
        ?float $final
    ): void {
        $schein = $fsz->getValueSchein($user_id);
        $user_kurs = $kurs;

        if (is_array($matches[1]) && $fsz->getValueNiveau($user_id) && $schein === 'LB') {
            $user_niveau_span = '(' . end($matches[1]) . ', ' . sprintf('%s erreicht', $fsz->getValueNiveau($user_id)) . ')';
            $user_kurs = str_replace(end($matches[0]), $user_niveau_span, $kurs);
        }

        $col = 0;
        $this->worksheet->write_string($this->row, $col++, utf8_decode(FszNotenVerwaltung::FormatName($user_data['Nachname'] ?? '')), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, utf8_decode(FszNotenVerwaltung::FormatName($user_data['Vorname'] ?? '')), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, $user_data['Email'] ?? '', $this->data_format);
        $this->worksheet->write_string($this->row, $col++, $this->getDatafieldValue(MATRIKELNUMMER_ID, $user_data['user_id'] ?? ''), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, $fsz->getValueNiveau($user_id), $this->data_format);

        $final_word = $fsz->getValueErfolg($user_id) ? 'mit Erfolg bestanden' : $fsz->getGradeAsWord($final);
        $this->worksheet->write($this->row, $col++, $final ?? '', $this->data_format);
        $this->worksheet->write_string($this->row, $col++, $final_word, $this->data_format);

        $this->worksheet->write_string($this->row, $col++, $schein === 'LB' ? utf8_decode($seminar->ects ?? '') : '', $this->data_format);
        $this->worksheet->write_string($this->row, $col++, $schein, $this->data_format);

        $this->worksheet->write_string($this->row, $col++, utf8_decode($user_kurs), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, utf8_decode($sws), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, utf8_decode($dozent['Nachname'] ?? ''), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, utf8_decode($dozent['Vorname'] ?? ''), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, utf8_decode($one_semester['name'] ?? ''), $this->data_format);
        $this->worksheet->write_string($this->row, $col++, utf8_decode($sprache), $this->data_format);

        $this->row++;
    }

    private function getDatafieldValue(string $id, string $range_id): ?string
    {
        $db = DbManager::get();
        return $db->fetchColumn("SELECT content FROM datafields_entries WHERE datafield_id = ? AND range_id = ?", [$id, $range_id]) ?? null;
    }
}
