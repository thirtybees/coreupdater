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
<div class="panel" id="process-result">
  <div class="panel-heading">
    {l s='Your system has been updated' mod='coreupdater'}
  </div>

  <div class="alert alert-success">
    <h2>{l s='Your system has been updated!' mod='coreupdater'}</h2>
    <p>
      {l s='Congratulation, your system has been updated to [1]%s[/1] version [2]%s[/2].' sprintf=[$versionType, $versionName] tags=['<b>', '<b>'] mod='coreupdater'}
      <br />
      {l s='Please reload the page and enjoy the new version' mod='coreupdater'}
    </p>
  </div>
  <div>
    <a href="javascript:location.reload();" class="btn btn-primary">
      {l s='Reload page' mod='coreupdater'}
    </a>
  </div>
</div>
