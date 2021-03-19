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
    {if !$edits && !$changes}
      {l s='Your system is updated' mod='coreupdater'}
    {else}
      {l s='Update your store' mod='coreupdater'}
    {/if}
  </div>

  {if $sameRevision}
    {if !$edits && !$changes}
      <div class="alert alert-success">
        <h2>{l s='Your system is up to date!' mod='coreupdater'}</h2>
        <p>
          {l s='You are already using latest [1]%s[/1] version [2]%s[/2].' sprintf=[$versionType, $installedRevision] tags=['<b>', '<b>'] mod='coreupdater'}
        </p>
        <p>
          {l s='No further actions are required.' mod='coreupdater'}
        </p>
      </div>
    {else}
      <div class="alert alert-success">
        <h2>{l s='You are using latest version!' mod='coreupdater'}</h2>
        <p>
          {l s='You are already using latest [1]%s[/1] version [2]%s[/2].' sprintf=[$versionType, $installedRevision] tags=['<b>', '<b>'] mod='coreupdater'}
        </p>
      </div>
    {/if}
  {else}
    {if !$edits && !$changes}
      <div class="alert alert-success">
        <h2>{l s='Your system is up to date!' mod='coreupdater'}</h2>
        <p>
          {l s='You are already using latest [1]%s[/1] version [2]%s[/2].' sprintf=[$versionType, $installedRevision] tags=['<b>', '<b>'] mod='coreupdater'}
        </p>
        <p>
          {l s='No futher actions are required.' mod='coreupdater'}
        </p>
      </div>
    {else}
      <div class="alert alert-success">
        <h2>{l s='New version available' mod='coreupdater'}</h2>
        <p>
          {l s='There is a new [1]%s[/1] version [1]%s[/1] available.' sprintf=[$versionType, $targetRevision] tags=['<b>', '<b>'] mod='coreupdater'}
        </p>
      </div>
    {/if}
  {/if}


  {if $edits}
    <div class="alert alert-warning">
      <h2>{l s='You have local changes!' mod='coreupdater'}</h2>
      <p>
        {l s='Oh, bummer. Some of thirty bees core files have been [1]modified[/1]. That makes it a little bit harder to update your store.' tags=['<b>'] mod='coreupdater'}
      </p>
      <p>
        {l s='Modification of core files is not recommended. It makes it very hard to keep your store updated.' tags=['<b>'] mod='coreupdater'}
        {l s='You should [1]extract[/1] your modifications to overrides or to module. If unsure how to do that, please contact [2]thirty bees support[/2], we can help.' tags=['<b>', '<a href="https://thirtybees.com/contact/" target="_blank">'] mod='coreupdater'}
      </p>
      <p>
        {l s='Please note that by updating your store, your local modifications [1]will be overwritten[/1].' tags=['<b>'] mod='coreupdater'}
        {l s='Core updater will [1]backup[/1] all those files before update, though.' tags=['<b>'] mod='coreupdater'}
      </p>
    </div>
  {/if}

  {if $sameRevision && $changes}
    <div class="alert alert-warning">
      <h2>{l s='Your installation is broken!' mod='coreupdater'}</h2>
      <p>
        {l s='We have detected some [1]problems[/1] with your installation. You should fix them by updating your store.' tags=['<b>'] mod='coreupdater'}
      </p>
      {if !$edits}
        <p>
          {l s='Note that it is [1]safe[/1] to update your store because there are no local changes' tags=['<b>'] mod='coreupdater'}
        </p>
      {/if}
    </div>
  {/if}

  {if !$sameRevision && $changes && !$edits}
    <div class="alert alert-success">
      <h2>{l s='It is safe to update!' mod='coreupdater'}</h2>
      <p>
        {l s='You can [1]safely[/1] update your store to new version because there are no local changes' tags=['<b>'] mod='coreupdater'}
      </p>
    </div>
  {/if}

  {if $changeSet['change']}
    <p>
      <h4>{l s='Changed files' mod='coreupdater'}</h4>
      <ul class="file-list">
        {foreach from=$changeSet['change'] key=$file item=$modified}
          <li>
            <code>{$file|escape:'html'}</code>
            {if $modified}
              <span class="badge badge-warning">{l s='modified' mod='coreupdater'}</span>
            {/if}
          </li>
        {/foreach}
      </ul>
    </p>
  {/if}

  {if $changeSet['add']}
    <p>
      <h4>{l s='Missing files' mod='coreupdater'}</h4>
      <ul class="file-list">
        {foreach from=$changeSet['add'] key=$file item=$modified}
          <li>
            <code>{$file|escape:'html'}</code>
            {if $modified}
              <span class="badge badge-warning">{l s='modified' mod='coreupdater'}</span>
            {/if}
          </li>
        {/foreach}
      </ul>
    </p>
  {/if}

  {if $changeSet['remove']}
    <p>
      <h4>{l s='Extra files' mod='coreupdater'}</h4>
      <ul class="file-list">
        {foreach from=$changeSet['remove'] key=$file item=$modified}
          <li>
            <code>{$file|escape:'html'}</code>
            {if $modified}
              <span class="badge badge-warning">{l s='modified' mod='coreupdater'}</span>
            {/if}
          </li>
        {/foreach}
      </ul>
    </p>
  {/if}

  {if $edits || $changes}
    <div class="panel-footer">
      <button id="update-button" type="submit" class="btn btn-default pull-right" name="UPDATE">
        <i class="process-icon-upload"></i>
        {l s='Update store' mod='coreupdater'}
      </button>
    </div>
    <script type="application/javascript">
      $("#update-button").click(function() {
        coreUpdater.update("{$compareProcessId}");
      });
    </script>
  {/if}
</div>
