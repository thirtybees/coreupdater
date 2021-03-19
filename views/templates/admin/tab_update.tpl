{**
 * Copyright (C) 2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 *}
{strip}

  {assign var="translations" value=[
    "INITIALIZING" => "{l s='Initializing...' mod='coreupdater'}",
    "CHECKING_YOUR_INSTALLATION" => "{l s='Checking your installation' mod='coreupdater'}",
    "CHECKING_DESCRIPTION" => "{l s='Core updater is currently comparing your installation with [1]%s[/1] version [2]%s[/2]' sprintf=[$targetVersion.type, $targetVersion.version] tags=['<b>', '<b>'] mod='coreupdater'}",
    "UPDATE" => "{l s='Updating your store' mod='coreupdater'}",
    "UPDATE_DESCRIPTION" => "{l s='Core updater is currently updating your installation to [1]%s[/1] version [2]%s[/2]' sprintf=[$targetVersion.type, $targetVersion.version] tags=['<b>', '<b>'] mod='coreupdater'}"
  ]}

{/strip}
<div class="row">
  <div class="col-lg-12">
    <div class="panel" id="panel-progress">
      <div class="panel-heading" id="progress-header"></div>
      <p>
      <p class="alert alert-info" id="progress-description"></p>
      <div class="progress">
        <div class="progress-bar" id="progress-bar" style="width:0%">0.0%</div>
      </div>
      <p id="progress-bar-text" class="text-muted"></p>
    </div>
  </div>
</div>
</div>
<div class="row">
  <div class="col-lg-12">
    <div id="result"></div>
  </div>
</div>

<script type="application/javascript">
    $(document).ready(function () {
        window.coreUpdater = initializeCoreUpdater({$translations|json_encode});

        coreUpdater.compare({$process|json_encode});
    });
</script>
