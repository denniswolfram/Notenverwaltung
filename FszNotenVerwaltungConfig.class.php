<?php

class FszNotenVerwaltungConfig
{
    public string $seminar_id;
    public array $data = [];
    public array $marks = [];
    public array $conf = ['NOTIFY_USER' => 0, 'VISIBLE' => 0, 'PART_VISIBLE' => 0, 'UNICERT' => 0];

    public function __construct(string $seminar_id)
    {
        $this->seminar_id = $seminar_id;
        $this->restore();
    }

    public function getNewId(): string
    {
        return md5(uniqid('FszNotenVerwaltung', true));
    }

    public function restore(): void
    {
        $db = DbManager::get();
        $this->data = [];
        $this->marks = [];
        $this->conf = [];

        $this->data = $db->fetchGrouped(
            "SELECT * FROM fsz_notenverwaltung_config WHERE seminar_id = ? ORDER BY name",
            [$this->seminar_id]
        );

        foreach ($this->data as $id => $data) {
            [$name, $bezug] = explode('-', $data['name'] ?? '-');
            if ($name === 'MARK') {
                $this->marks[$id]['desc'] = $data['value'];
                $this->data[$id]['name'] = 'MARK';
            } elseif ($name === 'WEIGHT') {
                $this->marks[$bezug]['weight'] = $data['value'];
            } else {
                $this->conf[$name] = $data['value'];
            }
        }
    }

    public function store(): int
    {
        $db = DbManager::get();
        $position = 0;
        $ret = 0;

        foreach ($this->marks as $id => $data) {
            $this->data[$id]['id'] = $id;
            $this->data[$id]['seminar_id'] = $this->seminar_id;
            $this->data[$id]['name'] = 'MARK-' . ++$position;
            $this->data[$id]['value'] = $data['desc'];

            $wid = md5('WEIGHT-' . $id);
            $this->data[$wid]['id'] = $wid;
            $this->data[$wid]['seminar_id'] = $this->seminar_id;
            $this->data[$wid]['name'] = 'WEIGHT-' . $id;
            $this->data[$wid]['value'] = $data['weight'];
        }

        foreach ($this->conf as $name => $value) {
            $id = md5($name . $this->seminar_id);
            $this->data[$id]['id'] = $id;
            $this->data[$id]['seminar_id'] = $this->seminar_id;
            $this->data[$id]['name'] = $name;
            $this->data[$id]['value'] = (string)$value;
        }

        foreach ($this->data as $row) {
            $ret += $db->execute(
                "REPLACE INTO fsz_notenverwaltung_config (id, seminar_id, name, value) VALUES (?, ?, ?, ?)",
                [
                    $row['id'],
                    $row['seminar_id'],
                    $row['name'],
                    $row['value']
                ]
            );
        }

        $this->restore();
        return $ret;
    }

    public function deleteMark(string $mark_id): int
    {
        $ret = 0;
        if (isset($this->marks[$mark_id])) {
            $db = DbManager::get();
            $db->execute("DELETE FROM fsz_notenverwaltung_noten WHERE config_id = ?", [$mark_id]);
            $ret = $db->execute(
                "DELETE FROM fsz_notenverwaltung_config WHERE id IN (?, ?)",
                [$mark_id, md5('WEIGHT-' . $mark_id)]
            );
            $this->restore();
        }
        return $ret;
    }
}
