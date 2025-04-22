<?php

require_once "FszNotenVerwaltungConfig.class.php";

#[\AllowDynamicProperties]
class FszNotenVerwaltung
{
    private string $seminar_id;
    private array $data = [];
    private array $members = [];
    private int $num_grades = 0;
    private int $num_schein = 0;
    private int $num_niveau = 0;
    private int $num_erfolg = 0;
    private bool $is_unicert;
    private FszNotenVerwaltungConfig $config;
    private array $unicert_marks = [
        'UCHV' => 'Hörverstehen',
        'UCLV' => 'Leseverstehen',
        'UCSA' => 'Schriftlicher Ausdruck',
        'UCMA' => 'Mündlicher Ausdruck'
    ];

    public function __construct(string $seminar_id)
    {
        $this->seminar_id = $seminar_id;
        $seminar = new Seminar($seminar_id);
        $this->members = SimpleCollection::createFromArray($seminar->getMembers('autor'))
                                         ->orderBy('Nachname, Vorname')
                                         ->getArrayCopy();
        $this->config = new FszNotenVerwaltungConfig($this->seminar_id);
        $this->is_unicert = $this->config->conf['UNICERT'] ?? false;
        $this->restore();
    }

    public function restore(): void
    {
        $db = DbManager::get();
        $this->data = [];
        $counters = [
            'grades' => 0,
            'schein' => 0,
            'niveau' => 0,
            'erfolg' => 0
        ];

        $data = $db->fetchAll(
            "SELECT su.user_id, fsz_c.id AS config_id, fsz_n.value, changed_by_user_id, fsz_n.chdate 
             FROM seminar_user su
             LEFT JOIN fsz_notenverwaltung_config fsz_c ON fsz_c.seminar_id = su.seminar_id AND fsz_c.name LIKE 'MARK%'
             LEFT JOIN fsz_notenverwaltung_noten fsz_n ON fsz_n.config_id = fsz_c.id AND fsz_n.user_id = su.user_id
             WHERE su.seminar_id = ? AND su.status = 'autor'", 
            [$this->seminar_id]
        );

        foreach ($data as $row) {
            $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
            if ($row['chdate']) {
                $counters['grades']++;
            }
        }

        foreach (['schein', 'niveau', 'erfolg'] as $key) {
            foreach ($db->fetchAll("SELECT * FROM fsz_notenverwaltung_noten WHERE config_id = ?", [md5($key . $this->seminar_id)]) as $row) {
                $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
                if ($row['chdate']) {
                    $counters[$key]++;
                }
            }
        }

        foreach (array_keys($this->unicert_marks) as $ucert) {
            foreach ($db->fetchAll("SELECT * FROM fsz_notenverwaltung_noten WHERE config_id = ?", [md5($ucert . $this->seminar_id)]) as $row) {
                $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
                if ($row['chdate']) {
                    $counters[$ucert] = ($counters[$ucert] ?? 0) + 1;
                }
            }
        }

        $this->num_grades = $counters['grades'];
        $this->num_schein = $counters['schein'];
        $this->num_niveau = $counters['niveau'];
        $this->num_erfolg = $counters['erfolg'];

        foreach (array_keys($this->unicert_marks) as $ucert) {
            $this->{'num_' . $ucert} = $counters[$ucert] ?? 0;
        }
    }

    public function getValue(string $user_id, string $config_id, string $field): mixed
    {
        return $this->data[$user_id . '-' . $config_id][$field] ?? null;
    }

    public function setValue(string $user_id, string $config_id, string $field, mixed $value): mixed
    {
        return ($this->data[$user_id . '-' . $config_id][$field] = $value);
    }

    public function getValueUnicert(string $user_id, string $unicert): ?string
    {
        $config_id = md5($unicert . $this->seminar_id);
        return $this->data[$user_id . '-' . $config_id]['value'] ?? null;
    }

    public function setValueUnicert(string $user_id, string $unicert, string $value): string
    {
        $config_id = md5($unicert . $this->seminar_id);
        $this->data[$user_id . '-' . $config_id]['user_id'] = $user_id;
        $this->data[$user_id . '-' . $config_id]['config_id'] = $config_id;
        return ($this->data[$user_id . '-' . $config_id]['value'] = $value);
    }

    // Other methods follow the same pattern...
}
