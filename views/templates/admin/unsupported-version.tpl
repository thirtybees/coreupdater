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
<div class="panel" id="unsupported-version-block">
  <div class="panel-heading">
    {l s='Unsupported module version!' mod='coreupdater'}
  </div>
  <div class="alert alert-danger">
    <h4 id="error-message">{l s='Unsupported module version!' mod='coreupdater'}</h4>
    <p>
      {l s='You have outdated version of Core Updater module. Thirty bees API server does not support version [1]%s[/1] anymore.' mod='coreupdater' sprintf=[$currentVersion] tags=['<b>']}
      <br />
      {l s='Please download and install the latest version [1]%s[/1]' mod='coreupdater' sprintf=[$latestVersion] tags=['<b>']}
    </p>
  </div>
  <p>
    <a class="btn btn-primary" href="https://github.com/thirtybees/coreupdater/releases/download/{$latestVersion}/coreupdater-v{$latestVersion}.zip">
      {l s='Download new version' mod='coreupdater'}
    </a>
  </p>
</div>
