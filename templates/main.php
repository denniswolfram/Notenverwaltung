<? if (count($fsz->members)): ?>
    <form action="<?= PluginEngine::getLink($plugin) ?>" method="POST">
        <? if ($plugin->fsz) : ?>
            <div>
                <?= Studip\LinkButton::create($plugin->_("Noten an Sekretariat schicken"), URLHelper::getURL('dispatch.php/messages/write', array(
                    'rec_uname'       => $fsz_admins,
                    'default_subject' => sprintf($plugin->_('%s: Kursresultate abgeschlossen'), $seminar->getName()),
                    'default_body'    => sprintf($plugin->_('Die Noten der Veranstaltung %s sind vollständig eingetragen.'), $seminar->getName()),
                    'emailrequest'    => true)), array('data-dialog' => '1')) ?>
            </div>
            <div>
                <?= $plugin->_("Eine Niveaustufe für alle Teilnehmer:") ?>
                &nbsp;
                <select name="niveau_default">
                    <option></option>
                    <? foreach (array('A1', 'A2', 'B1', 'B2', 'C1', 'C2') as $n) : ?>
                        <option <?= ($default_niveau == $n ? 'selected' : '') ?>><?= $n ?></option>
                    <? endforeach; ?>
                </select>
                <? if (!$plugin->is_unicert) : ?>
                <br>
                <?= $plugin->_("Unterschiedliche Niveaustufen für jeden Teilnehmer:") ?>
                &nbsp;
                <input type="checkbox" name="no_niveau_default"
                       value="1" <?= ($default_niveau !== null ? '' : 'checked') ?>>
                &nbsp;<? endif ?>
                &nbsp;<?= Studip\Button::create($plugin->_("Niveau setzen"), 'set_niveau_default'); ?>
            </div>
        <? endif ?>
        <table class="default zebra sortable-table" id="fsznotenverwaltung" data-table-id="fsznotenverwaltung" data-sort-list="[[1, 0]]">
            <thead>
                <tr>
                    <th width="1%"><input title="Alle auswählen" type="checkbox" name="all" value="1" data-proxyfor=":checkbox[name^=export]"></th>
                    <th data-sort="text" width="5%"><?= $plugin->_("Nachname") ?></th>
                    <th data-sort="text" width="5%"><?= $plugin->_("Vorname") ?></th>
                    <? if ($plugin->fsz) : ?>
                        <th data-sort="text" width="1%"><?= $plugin->_("Niveau") ?></th>
                        <th data-sort="text" width="1%"><?= $plugin->_("TS/LB") ?></th>
                        <? if (!$plugin->is_unicert) : ?>
                            <th data-sort="htmldata" width="1%"><?= $plugin->_("mit Erfolg bestanden") ?></th>
                        <? endif ?>
                    <? endif ?>
                    <?
                    if (!$plugin->is_unicert) {
                        foreach ($fsz->config->marks as $mark) {
                            ++$c;
                            echo '<th data-sort="htmldata" style="text-align:center" width="10%">' . htmlReady(($mark['desc'] ? $mark['desc'] : $c . '. Einzelnote') . ' (' . $mark['weight'] . '%)') . '</th>';
                        }
                    } else {
                        foreach ($fsz->unicert_marks as $mark => $desc) {
                            echo '<th data-sort="htmldata" style="text-align:center" width="10%">' . htmlReady($desc) . '</th>';
                        }
                    }
                    ?>
                    <th data-sort="htmldata" style="text-align:center" width="10%"><?= $plugin->_("Gesamtnote") ?></th>
                    <th data-sort="htmldata" style="text-align:center" width="5%"><?= $plugin->_("geändert") ?></th>
                </tr>
            </thead>
            <tbody>
                <?
                foreach ($fsz->members as $user_id => $user_data) {
                    if ($plugin->config->conf['INVISIBLE_WITHOUT_NOTE'] && !$fsz->hasValues($user_id)) continue;
                    $erfolg = $fsz->getValueErfolg($user_id);
                ?>
                    <tr>
                        <td><input type="checkbox" name="export[]" value="<?= $user_id ?>"></td>
                        <td> <?= htmlReady($user_data['Nachname']) ?></td>
                        <td> <?= htmlReady($user_data['Vorname']) ?></td>
                        <? if ($plugin->fsz) : ?>
                            <td style="text-align:center">
                                <? if ($default_niveau !== null) : ?>
                                    <input name="niveau[<?= $user_id ?>]" type="hidden"
                                           value="<?= $fsz->getValueNiveau($user_id) ?>">
                                    <?= $fsz->getValueNiveau($user_id) ?>
                                <? else : ?>
                                    <select name="niveau[<?= $user_id ?>]">
                                        <option></option>
                                        <? foreach (array('A1', 'A2', 'B1', 'B2', 'C1', 'C2') as $n) : ?>
                                            <option <?= ($fsz->getValueNiveau($user_id) == $n ? 'selected' : '') ?>><?= $n ?></option>
                                        <? endforeach; ?>
                                    </select>
                                <? endif; ?>
                            </td>

                            <td style="text-align:center">
                                <select name="schein[<?= $user_id ?>]">
                                    <option></option>
                                    <option <?= ($fsz->getValueSchein($user_id) == 'TS' ? 'selected' : '') ?>>TS</option>
                                    <option <?= ($fsz->getValueSchein($user_id) == 'LB' ? 'selected' : '') ?>>LB</option>
                                </select>
                            </td>
                            <? if (!$plugin->is_unicert) : ?>
                                <td style="text-align: center" data-sort-value="<?= ($erfolg ? '1' : '0') ?>">
                                    <input type="hidden" name="erfolg[<?= $user_id ?>]" value="0">
                                    <input type="checkbox"
                                           onChange="jQuery('input[name^=&quot;usermark[<?= $user_id ?>&quot;]').val('').attr('readonly', this.checked);"
                                           name="erfolg[<?= $user_id ?>]" value="1" <?= ($erfolg ? 'checked' : '') ?>>
                                </td>
                            <? endif ?>
                        <? endif ?>
                        <?
                        if (!$plugin->is_unicert) {
                            foreach (array_keys($fsz->config->marks) as $mid) {
                                echo '<td style="text-align:center" data-sort-value="' . $fsz->getValue($user_id, $mid, 'value') . '">
        <input type="text" ' . ($erfolg ? 'readonly' : '') . ' size="2" maxlength="3"  data-check-valid="' . $plugin->fsz . '" name="usermark[' . $user_id . '-' . $mid . ']" value="' . $fsz->getValue($user_id, $mid, 'value') . '"></td>';
                            }
                        } else {
                            foreach (array_keys($fsz->unicert_marks) as $mid) {
                                echo '<td style="text-align:center" data-sort-value="' . $fsz->getValueUnicert($user_id, $mid) . '">
        <input type="text" ' . ($erfolg ? 'readonly' : '') . ' size="2" maxlength="3"  data-check-valid="' . $plugin->fsz . '" name="' . $mid . '[' . $user_id .']" value="' . $fsz->getValueUnicert($user_id, $mid) . '"></td>';
                            }
                        }
                        if (!$erfolg) {
                            $final = $fsz->getFinalGrade($user_id);
                            if ($final !== null) $final = round($final, 1);
                            else $final = '-';
                        } else {
                            $final = $plugin->_("mit Erfolg bestanden");
                        }
                        [$last_changed, $last_changed_user_id] = array_values($fsz->getLastChanged($user_id));
                        ?>
                        <td style="text-align:center" data-sort-value="<?=$final?>">
                            <span>
                                <?=$final?>
                            </span>
                        </td>
                        <td style="text-align:center" data-sort-value="<?=$last_changed?>">
                            <span style="white-space: nowrap"><?=$last_changed ? strftime('%x %R', $last_changed) : '-'?></span>
                        </td>
                    </tr>
                <? } ?>
            </tbody>
        </table>
        <div align="center" style="margin-bottom:10px;">
            <?= Studip\Button::createAccept($plugin->_("Eingaben abspeichern"), 'save'); ?>
            &nbsp;
            <?= Studip\LinkButton::createCancel($plugin->_("Eingabe abbrechen"), PluginEngine::getUrl($plugin)) ?>
        </div>
    </form>
<? endif ?>
<script>
 jQuery(function($) {
     $(document)
         .on('click', 'a[data-exportlink]',
             function (event) {
                 let checked = $('#fsznotenverwaltung :checkbox[name^=export]:checked');
                 if (checked.length) {
                     var target = $(event.target);
                     if (!target.data('orighref')) {
                         target.data('orighref', target.attr('href'));
                     }
                     target.attr('href', target.data('orighref') + '&' + checked.serialize());
                 }
             })
            .on('change', 'input[data-check-valid]',
            function (event) {
                let valid = [1,1.3,1.7,2,2.3,2.7,3,3.3,3.7,4,5];
                if ($(this).data('check-valid') === 1) {
                    let to_check = parseFloat($(this).val().toString().replace(',', '.'));
                    if (!valid.includes(to_check)) {
                        if ($(this).val() !== '') {
                            $(this)[0].setCustomValidity('<?=$plugin->_('Sie haben eine ungültige Note eingegeben, gültige Noten sind:')?> ' + valid.join('; '));
                        } else {
                            $(this)[0].setCustomValidity('');
                        }
                } else {
                        $(this)[0].setCustomValidity('');
                    }
                }
            })
     ;
 });
</script>
