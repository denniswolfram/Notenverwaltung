<?php if (empty($_SESSION['messages'])): ?>
    <div style="margin:20px;">
        <!-- Header: Noten für -->
        <div style="font-size:120%; font-weight:bold;">
            <?php echo $plugin->_("Noten für:") . '&nbsp;' . htmlspecialchars($fsz->members[$user_id]['fullname']); ?>
        </div>

        <!-- Optional: Niveau -->
        <?php if ($plugin->fsz): ?>
            <div style="font-size:120%; font-weight:bold;">
                <?php echo $plugin->_("Niveau:") . '&nbsp;' . htmlspecialchars($fsz->getValueNiveau($user_id)); ?>
            </div>
        <?php endif; ?>

        <!-- Einzelnoten -->
        <ol>
            <?php foreach ($fsz->config->marks as $mark_id => $mark_data): ?>
                <li>
                    <span style="width:200px;">
                        <?php echo htmlspecialchars($mark_data['desc'] ?: $plugin->_("Einzelnote")); ?>
                        &nbsp;
                        (<?php echo htmlspecialchars($mark_data['weight']); ?>%):
                    </span>
                    <?php echo htmlspecialchars($fsz->getValue($user_id, $mark_id, 'value')); ?>
                </li>
            <?php endforeach; ?>

            <!-- Gesamtnote oder Erfolg -->
            <li style="list-style-type:none; margin-top:20px;">
                <?php if ($fsz->getValueErfolg($user_id)): ?>
                    <span style="width:200px;">
                        <?php echo $plugin->_("Mit Erfolg bestanden"); ?>
                    </span>
                <?php elseif ($fsz->getFinalGrade($user_id)): ?>
                    <span style="width:200px;">
                        <?php echo $plugin->_("Gesamtnote:"); ?>
                    </span>
                    <?php
                    $grade = round($fsz->getFinalGrade($user_id), 1);
                    echo htmlspecialchars($grade);
                    ?>
                    &nbsp;
                    <?php if ($plugin->fsz): ?>
                        (<?php echo htmlspecialchars($fsz->getGradeAsWord($grade)); ?>)
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        </ol>
    </div>
<?php endif; ?>
