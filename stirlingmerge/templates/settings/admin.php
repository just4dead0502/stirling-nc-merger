<?php
/** @var array $_ */
$urlValue     = htmlspecialchars($_['stirling_url'] ?? '');
$apiKeyValue  = htmlspecialchars($_['stirling_api_key'] ?? '');
?>
<div id="stirlingmerge-admin-settings">
    <h2><?php p($l->t('Merge to PDF — Stirling PDF settings')); ?></h2>

    <p class="settings-hint">
        <?php p($l->t('Configure the Stirling PDF backend used to convert images to PDF.')); ?>
    </p>

    <div id="stirlingmerge-save-msg" style="display:none;padding:8px 0;font-weight:bold;"></div>

    <table class="grid">
        <tbody>
            <tr>
                <td><label for="stirlingmerge-url"><?php p($l->t('Stirling PDF URL')); ?></label></td>
                <td>
                    <input id="stirlingmerge-url"
                           type="url"
                           name="stirling_url"
                           value="<?php p($urlValue); ?>"
                           placeholder="http://stirling-pdf:8080"
                           style="width:320px;" />
                    <span class="icon-info" title="<?php p($l->t('Base URL of your Stirling PDF instance, without trailing slash.')); ?>"></span>
                </td>
            </tr>
            <tr>
                <td><label for="stirlingmerge-apikey"><?php p($l->t('API Key')); ?></label></td>
                <td>
                    <input id="stirlingmerge-apikey"
                           type="password"
                           name="stirling_api_key"
                           value="<?php p($apiKeyValue); ?>"
                           placeholder="<?php p($l->t('Leave empty if authentication is disabled')); ?>"
                           style="width:320px;" />
                </td>
            </tr>
        </tbody>
    </table>

    <br/>
    <button id="stirlingmerge-save" class="button"><?php p($l->t('Save')); ?></button>
    <button id="stirlingmerge-test" class="button" style="margin-left:8px;"><?php p($l->t('Test connection')); ?></button>
    <span id="stirlingmerge-test-result" style="margin-left:12px;"></span>
</div>
<!-- JS loaded via AdminSettings::getForm() → OCP\Util::addScript to satisfy NC32 CSP -->
