<? if(empty($_SESSION['messages'])): ?>
    <div style="margin:20px;">
        <div style="font-size:120%;font-weight:bold;"><?=$plugin->_("Noten für:") . '&nbsp;' . htmlReady($fsz->members[$user_id]['fullname'])?></div>
        <? if ($plugin->fsz): ?>
            <div style="font-size:120%;font-weight:bold;"><?=$plugin->_("Niveau:") . '&nbsp;' . htmlReady($fsz->getValueNiveau($user_id))?></div>
        <? endif ?>
        <ol>
            <? foreach ($fsz->config->marks as $mark_id => $mark_data): ?>
                <li >
                    <span style="width:200px;">
                        <?=htmlReady(($mark_data['desc'] ? $mark_data['desc'] : $plugin->_("Einzelnote")))?>
                        &nbsp;
                        (<?=htmlReady($mark_data['weight'])?>%):
                    </span>
                    <?=$fsz->getValue($user_id, $mark_id, 'value')?>
                </li>
            <? endforeach ?>
            <li style="list-style-type:none; margin-top:20px;">
                <? if ($fsz->getValueErfolg($user_id)): ?>
                    <span style="width:200px;">
                        <?=$plugin->_("Mit Erfolg bestanden") ?>
                    </span>
                <? elseif ($fsz->getFinalGrade($user_id)): ?>
                    <span style="width:200px;">
                        <?=$plugin->_("Gesamtnote:") ?>
                    </span>
                    <?=($grade = round($fsz->getFinalGrade($user_id),1))?>
                    &nbsp;<? if ($plugin->fsz): ?>
                        (<?=$fsz->getGradeAsWord($grade)?>)
                    <? endif ?>
                <? endif ?>
            </li>
        </ol>
    </div>
<? endif ?>
