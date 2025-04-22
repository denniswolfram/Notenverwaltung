<?php
require_once "FszNotenVerwaltung.class.php";
require_once "lib/classes/Seminar.class.php";

require_once "vendor/write_excel/OLEwriter.php";
require_once "vendor/write_excel/BIFFwriter.php";
require_once "vendor/write_excel/Worksheet.php";
require_once "vendor/write_excel/Workbook.php";

define('MATRIKELNUMMER_ID','ec4d5347bd30ade7dd23165ba9ebd446');
define('SWS_ID','e5d95786510257dd4abb4b46cbd804a3');

class FszNotenVerwaltungExcelExporter {

    var $sprachen = array(
        'DE' => 'Deutsch',
        'EN' => 'Englisch',
        'FR' => 'Französisch',
        'SP' => 'Spanisch',
        'IT' => 'Italienisch',
        'JA' => 'Japanisch',
        'RU'=>'Russisch',
        'PO'=>'Portugisisch',
        'LT'=>'Latein',
        'CH'=>'Chinesich',
        'SW'=>'Schwedisch',
        'GA'=>'Altgriechisch',
        'GN'=>'Neugriechisch',
        'PL'=>'Polnisch',
        'TR'=>'Türkisch'
    );
    var $workbook;
    var $to_export = array();

    function __construct($to_export = array())
    {
        $this->to_export = $to_export;
    }

    function addExportSeminar($id){
        if(!in_array($id, $this->to_export)) $this->to_export[] = $id;
    }

    function createExcelSheet($user_ids = array()){
        $tmpfile = $GLOBALS['TMP_PATH'] . '/' . md5(uniqid('write_excel',1));
        // Creating a workbook
        $workbook = new Workbook($tmpfile);

        $caption_format = $workbook->addformat();
        $caption_format->set_size(10);
        $caption_format->set_align("left");
        $caption_format->set_align("vcenter");
        $caption_format->set_bold();
        //$caption_format->set_text_wrap();

        $data_format = $workbook->addformat();
        $data_format->set_size(10);
        $data_format->set_align("left");
        $data_format->set_align("vcenter");

        // Creating the first worksheet
        $worksheet1 = $workbook->addworksheet("Notenverwaltung");
        $row = 0;
        $c = 0;
        $worksheet1->write_string($row,$c++, "Nachname", $caption_format);
        $worksheet1->write_string($row,$c++, "Vorname", $caption_format);
        $worksheet1->write_string($row,$c++, "Email", $caption_format);
        $worksheet1->write_string($row,$c++, "MatriNr", $caption_format);
        $worksheet1->write_string($row,$c++, "Niveau", $caption_format);
        $worksheet1->write_string($row,$c++, "NoteZahl", $caption_format);
        $worksheet1->write_string($row,$c++, "NoteWort", $caption_format);
        $worksheet1->write_string($row,$c++, "ECTS", $caption_format);
        $worksheet1->write_string($row,$c++, "Schein", $caption_format);
        $worksheet1->write_string($row,$c++, "Leistung", $caption_format);
        $worksheet1->write_string($row,$c++, "BelegNr", $caption_format);
        $worksheet1->write_string($row,$c++, "Kurs", $caption_format);
        $worksheet1->write_string($row,$c++, "SWS", $caption_format);
        $worksheet1->write_string($row,$c++, "DozName", $caption_format);
        $worksheet1->write_string($row,$c++, "DozVorname", $caption_format);
        $worksheet1->write_string($row,$c++, "Semester", $caption_format);
        $worksheet1->write_string($row,$c++, "Sprache", $caption_format);
        $fsz = new FszNotenVerwaltung($this->to_export[0]);
        foreach ($fsz->unicert_marks as $ucert => $uname) {
            $worksheet1->write_string($row,$c++, utf8_decode($ucert), $caption_format);
        }
        $worksheet1->write_string($row,$c++, utf8_decode("Geändert am"), $caption_format);
        $worksheet1->write_string($row,$c++, utf8_decode("Geändert von"), $caption_format);
            ++$row;
        $this->workbook = $workbook;
        $this->worksheet = $worksheet1;
        $this->caption_format = $caption_format;
        $this->data_format = $data_format;
        $this->row = $row;
        foreach($this->to_export as $id) $this->exportOneSeminar($id, $user_ids);
        $workbook->close();
        return $tmpfile;
    }

    function exportOneSeminar($id,$user_ids = array()){
        $fsz = new FszNotenVerwaltung($id);
        $fsz->fsz = true;
        if(!($fsz->num_schein || $fsz->num_grades)) return;
        $seminar = new Seminar($fsz->seminar_id);
        $semester = SemesterData::GetInstance();
        $one_semester = $semester->getSemesterDataByDate($seminar->getSemesterStartTime());
        $dozent = current($seminar->getMembers('dozent'));
        $sws = $this->getDatafieldValue(SWS_ID,$fsz->seminar_id);
        if($kurs = trim(strstr($seminar->getName(),' '))){
            $belegnr = trim(strstr($seminar->getName(),' ',true));
            $sprache = $this->sprachen[substr($belegnr,0,2)];
        } else {
            $kurs = $seminar->getName();
        }
        preg_match_all("/\(([^\)]*)\)/", $kurs, $matches);
        foreach($fsz->members as $user_id => $user_data){
            if (count($user_ids) && !in_array($user_id, $user_ids)) continue;
            $final = $fsz->getFinalGrade($user_id);
            if(!($final || $fsz->getValueSchein($user_id) || $fsz->getValueErfolg($user_id))) continue;
            $schein = $fsz->getValueSchein($user_id);
            if (is_array($matches[1]) && $fsz->getValueNiveau($user_id) && $schein == 'LB') {
                $user_niveau_span = '(' . end($matches[1]) . ', ' . sprintf('%s erreicht', $fsz->getValueNiveau($user_id)) . ')';
                $user_kurs = str_replace(end($matches[0]), $user_niveau_span, $kurs);
            } else
            $user_kurs = $kurs;
            $c = 0;
            $this->worksheet->write_string($this->row, $c++, utf8_decode(FszNotenVerwaltung::FormatName($user_data['Nachname'])), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, utf8_decode(FszNotenVerwaltung::FormatName($user_data['Vorname'])), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, $user_data['Email'], $this->data_format);
            $this->worksheet->write_string($this->row, $c++, $this->getDatafieldValue(MATRIKELNUMMER_ID,$user_data['user_id']), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, $fsz->getValueNiveau($user_id), $this->data_format);

            if($final) $final = round($final,1);
            else $final = '';
            krsort($changed, SORT_NUMERIC);
            list($last_changed, $last_changed_user_id) = array_values($fsz->getLastChanged($user_id));
            if ($fsz->getValueErfolg($user_id)) {
                $final = '';
                $final_word = 'mit Erfolg bestanden';
            } else {
                $final_word = $fsz->getGradeAsWord($final);
            }
            $this->worksheet->write($this->row, $c++, $final , $this->data_format);
            $this->worksheet->write_string($this->row, $c++, $final_word , $this->data_format);
            $this->worksheet->write_string($this->row, $c++, ($schein == 'LB' ? utf8_Decode($seminar->ects) : ''), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, $schein, $this->data_format);
            $this->worksheet->write_string($this->row, $c++, ($schein == 'LB' ? utf8_Decode($seminar->leistungsnachweis) : ''), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, $belegnr, $this->data_format);
            $this->worksheet->write_string($this->row, $c++, utf8_decode($user_kurs), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, utf8_decode($sws), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, utf8_decode($dozent['Nachname']), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, utf8_decode($dozent['Vorname']), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, utf8_decode($one_semester['name']), $this->data_format);
            $this->worksheet->write_string($this->row, $c++, utf8_decode($sprache), $this->data_format);
            foreach ($fsz->unicert_marks as $ucert => $uname) {
                $this->worksheet->write_string($this->row, $c++, $fsz->is_unicert ? str_replace('.', ',', $fsz->getValueUnicert($user_id, $ucert)) : '', $this->data_format);
            }
            $this->worksheet->write_string($this->row, $c++, ($last_changed > 0 ? date("d.m.Y G:i", $last_changed) : '') , $this->data_format);
            $this->worksheet->write_string($this->row, $c++, ($last_changed_user_id ? utf8_decode(get_fullname($last_changed_user_id)) : '') , $this->data_format);
                                                        ++$this->row;
        }
    }

    function getDatafieldValue($id, $range_id){
        $db = DbManager::get();
        return $db->fetchColumn("SELECT content FROM datafields_entries WHERE datafield_id=? AND range_id=?", array($id,$range_id));
    }
}
