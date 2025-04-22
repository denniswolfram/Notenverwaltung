<form action="<?=PluginEngine::getLink($plugin, array('action' => 'config')) ?>" method="POST">
    <table class="default nohover">
        <tr>
            <th><?=$plugin->_("Einstellungen")?></th>
        </tr>
        <tr>
            <td>
                <label>
                    <?=$plugin->_("Angezeigter Name in der Menüleiste: ")?>
                    <input placeholder="<?=htmlReady($plugin->displayname)?>" type="text" name="title" size="50" value="<?=$config->conf['TITLE']?>">
                </label>
            </td>
        </tr>
        <tr>
            <td>
                <?= $plugin->_("Eigene Benotung für Teilnehmer sichtbar:")?>
                <br>
                <input type="radio" id="fsz_not_visible" name="visible" style="vertical-align:middle" value="0" <?=(! $config->conf['VISIBLE'] AND ! $config->conf['PART_VISIBLE']) ? 'checked' : ''?>>
                <label for="not_visible"><?= $plugin->_("nie")?></label><br>
                <input type="radio" id="fsz_complete_visible" name="visible" style="vertical-align:middle" value="complete" <?=$config->conf['VISIBLE'] ? 'checked' : ''?>>
                <label for="fsz_complete_visible"><?= $plugin->_("wenn Benotung abgeschlossen")?></label><br>
                <input type="radio" id="fsz_part_visible" name="visible" style="vertical-align:middle" value="part" <?=$config->conf['PART_VISIBLE'] ? 'checked' : ''?>>
                <label for="fsz_part_visible"><?= $plugin->_("bereits für Teilnoten")?></label><br>
                <br>
            </td>
        </tr>
        <? if ($plugin->fsz) : ?>
            <tr>
                <td>
                    <label>
                        <?=$plugin->_("UNIcert Teilnoten: ")?>
                        <label><input type="radio" name="unicert" <?=$config->conf['UNICERT'] ? 'checked' : ''?> value="1"><?=$plugin->_("Ja")?></label>
                        <label><input type="radio" name="unicert" <?=!$config->conf['UNICERT'] ? 'checked' : ''?> value="0"><?=$plugin->_("Nein")?></label>

                    </label>
                </td>
            </tr>
        <? endif ?>
        <tr>
            <td>
                <?= $plugin->_("Einzelnoten mit Gewichtung:") ?>
            </td>
        </tr>
        <tr>
            <td>
                <ol>
                    <?foreach($config->marks as $mark_id => $mark_data){?>
                        <li>
                            <?=$plugin->_("Beschreibung:")?>
                            <input type="text" name="marks[<?=$mark_id?>][desc]" value="<?=htmlReady($mark_data['desc'])?>">
                            &nbsp;&nbsp;
                            <?=$plugin->_("Gewichtung:")?>
                            <input type="text" name="marks[<?=$mark_id?>][weight]" size="3" maxlength="4" value="<?=htmlReady($mark_data['weight'])?>">%
                            &nbsp;&nbsp;
                            <a href="<?=PluginEngine::getLink(
                                     $plugin,
                                     array(
                                         'action' => 'config',
                                         'delete_mark' => $mark_id
                                     )
                                     )?>">
                                <?= Icon::create('trash', 'info') ?>
                            </a>
                        </li>
                    <?}?>
                    <li style="list-style-type:none">
                        <?=Studip\Button::create( $plugin->_("Neue Note hinzufügen"), 'add_mark')?>
                    </li>
                </ol>
            </td>
        </tr>
        <tr>
            <td align="center">
                <?=Studip\Button::create( $plugin->_("Eingaben abspeichern"), 'save')?>
                &nbsp;
                <a href="<?=PluginEngine::getLink($plugin)?>">
                    <?=Studip\Button::create( $plugin->_("Eingaben abbrechen"), 'cancel')?>
                </a>
            </td>
        </tr>
    </table>
</form>
