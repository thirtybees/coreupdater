{**
 * Copyright (C) 2017-2024 thirty bees
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
 * @copyright 2017-2024 thirty bees
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
        {l s='Use [1]Review & merge[/1] on modified files to compare the original file, the local version, and the new version before you update.' tags=['<b>'] mod='coreupdater'}
      </p>
      {if $developerMode}
        <p>{l s='You are using Developer mode and additional preview / ignore tools are visible. Use them at your own risk and only if you know what they do.' mod='coreupdater'}</p>
      {/if}
      <p>
        {l s='Modification of core files is not recommended. It makes it very hard to keep your store updated.' tags=['<b>'] mod='coreupdater'}
        {l s='You should [1]extract[/1] your modifications to overrides or to module. If unsure how to do that, please contact [2]thirty bees support[/2], we can help.' tags=['<b>', '<a href="https://thirtybees.com/contact/" target="_blank">'] mod='coreupdater'}
      </p>
      <p>
        {l s='Please note that by updating your store, your local modifications [1]will be overwritten[/1] unless you explicitly keep them.' tags=['<b>'] mod='coreupdater'}
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
    <div class="file-section">
      <h4>{l s='Changed files' mod='coreupdater'}</h4>
      <p class="file-section-note">
        {l s='These files differ from the target version. Review local customizations before you update.' mod='coreupdater'}
      </p>
    </div>
    <ul class="file-list">
      {foreach from=$changeSet['change'] key='file' item='modified'}
        <li>
          <code>{$file|escape:'html'}</code>
          {if $modified}
            <span class="badge badge-warning">{l s='local customization' mod='coreupdater'}</span>
            {if !empty($previewableFiles[$file])}
              <a href="#" class="btn btn-default btn-xs review-file" data-file="{$file|escape:'html'}">{l s='Review & merge' mod='coreupdater'}</a>
              <span class="badge badge-info merge-state" data-file="{$file|escape:'html'}" style="display:none;">{l s='Merged' mod='coreupdater'}</span>
            {/if}
          {elseif $developerMode && !empty($previewableFiles[$file])}
            <a href="#" class="btn btn-default btn-xs preview-file" data-file="{$file|escape:'html'}">{l s='Review' mod='coreupdater'}</a>
          {/if}
          {if $developerMode}
            <label class="ignore-label"><input type="checkbox" class="ignore-file" data-file="{$file|escape:'html'}"> {l s='Skip this file in this update' mod='coreupdater'}</label>
          {/if}
        </li>
      {/foreach}
    </ul>
  {/if}

  {if $changeSet['add']}
    <div class="file-section">
      <h4>{l s='Missing files' mod='coreupdater'}</h4>
      <p class="file-section-note">
        {l s='These files exist in the new core version but are currently missing from your shop.' mod='coreupdater'}
      </p>
    </div>
    <ul class="file-list">
      {foreach from=$changeSet['add'] key='file' item='modified'}
        <li>
          <code>{$file|escape:'html'}</code>
          {if $modified}
            <span class="badge badge-warning">{l s='local customization' mod='coreupdater'}</span>
          {/if}
          {if $developerMode && !empty($previewableFiles[$file])}
            <a href="#" class="btn btn-default btn-xs preview-file" data-file="{$file|escape:'html'}">{l s='Review' mod='coreupdater'}</a>
          {/if}
          {if $developerMode}
            <label class="ignore-label"><input type="checkbox" class="ignore-file" data-file="{$file|escape:'html'}"> {l s='Skip this file in this update' mod='coreupdater'}</label>
          {/if}
        </li>
      {/foreach}
    </ul>
  {/if}

  {if $changeSet['remove']}
    <div class="file-section">
      <h4>{l s='Extra files' mod='coreupdater'}</h4>
      <p class="file-section-note">
        {l s='These files are present in your shop but do not exist in the version you are updating to.' mod='coreupdater'}
      </p>
    </div>
    <ul class="file-list">
      {foreach from=$changeSet['remove'] key='file' item='modified'}
        <li>
          <code>{$file|escape:'html'}</code>
          {if $modified}
            <span class="badge badge-warning">{l s='local customization' mod='coreupdater'}</span>
          {/if}
          {if $developerMode && !empty($previewableFiles[$file])}
            <a href="#" class="btn btn-default btn-xs preview-file" data-file="{$file|escape:'html'}">{l s='Review' mod='coreupdater'}</a>
          {/if}
          {if $developerMode}
            <label class="ignore-label"><input type="checkbox" class="ignore-file" data-file="{$file|escape:'html'}"> {l s='Keep this file during this update' mod='coreupdater'}</label>
          {/if}
        </li>
      {/foreach}
    </ul>
  {/if}

  {if $edits || $changes}
    <div class="panel-footer">
      <button id="update-button" type="submit" class="btn btn-default pull-right" name="UPDATE">
        <i class="process-icon-upload"></i>
        {l s='Update store' mod='coreupdater'}
      </button>
    </div>
  {/if}
</div>

<div id="file-preview-modal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="file-preview-title">{l s='File review' mod='coreupdater'}</h4>
        <p class="merge-file-path" id="file-preview-path"></p>
      </div>
      <div class="modal-body">
        <div id="file-preview-diff-container">
          <pre id="file-preview-diff" class="diff-content"></pre>
        </div>
        <div id="file-merge-container" style="display:none;">
          <div class="alert alert-info" id="file-merge-summary"></div>
          <div class="merge-toolbar">
            <button type="button" class="btn btn-default btn-sm" id="merge-select-local">{l s='Keep local version everywhere' mod='coreupdater'}</button>
            <button type="button" class="btn btn-default btn-sm" id="merge-select-incoming">{l s='Use new version everywhere' mod='coreupdater'}</button>
          </div>
          <div class="merge-instructions">
            {l s='For each highlighted change, click either the Local version card or the New version card. Save is enabled only after every change has a selection.' mod='coreupdater'}
          </div>
          <div class="merge-version-help">
            <span><strong>{l s='Local version' mod='coreupdater'}</strong> {l s='is the file currently in the shop, including merchant customizations.' mod='coreupdater'}</span>
            <span><strong>{l s='New version' mod='coreupdater'}</strong> {l s='is the file from the update target you are installing.' mod='coreupdater'}</span>
            <span><strong>{l s='Installed core version' mod='coreupdater'}</strong> {l s='is the official file for the currently installed core revision, before local customizations.' mod='coreupdater'}</span>
          </div>
          <div id="file-merge-chunks"></div>
          <div class="merge-result-panel">
            <h4>{l s='Merged file preview' mod='coreupdater'}</h4>
            <pre id="file-merge-result"></pre>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="file-merge-save" class="btn btn-primary" style="display:none;">{l s='Save merge for update' mod='coreupdater'}</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close' mod='coreupdater'}</button>
      </div>
    </div>
  </div>
</div>

<script type="application/javascript">
  var compareProcessId = "{$compareProcessId|escape:'javascript'}";
  var mergeTexts = {
    localVersion: "{l s='Local version' mod='coreupdater'|escape:'javascript'}",
    incomingVersion: "{l s='New version' mod='coreupdater'|escape:'javascript'}",
    installedCoreVersion: "{l s='Installed core version' mod='coreupdater'|escape:'javascript'}",
    localOnly: "{l s='Local customization only' mod='coreupdater'|escape:'javascript'}",
    incomingOnly: "{l s='Core update only' mod='coreupdater'|escape:'javascript'}",
    conflict: "{l s='Both changed' mod='coreupdater'|escape:'javascript'}",
    autoMerged: "{l s='Auto-merged' mod='coreupdater'|escape:'javascript'}",
    chooseVersion: "{l s='Choose Local version or New version below.' mod='coreupdater'|escape:'javascript'}",
    pendingChoice: "{l s='Selection required' mod='coreupdater'|escape:'javascript'}",
    mergeSaved: "{l s='Merge saved for this file. It will be written during update.' mod='coreupdater'|escape:'javascript'}",
    summaryTemplate: "{l s='This file contains [1]%s[/1] selectable change block(s), including [2]%s[/2] conflict(s). Choose the local or new version for each block, then save the merge before updating.' sprintf=['%s','%s'] tags=['<b>','<b>'] mod='coreupdater'|escape:'javascript'}",
    fallbackSummary: "{l s='Fine-grained merge detection was not available for this file, so the updater is offering a whole-file choice instead.' mod='coreupdater'|escape:'javascript'}",
    saveDisabled: "{l s='Select Local version or New version for every change before saving this merge.' mod='coreupdater'|escape:'javascript'}",
    previewTitle: "{l s='File preview' mod='coreupdater'|escape:'javascript'}",
    reviewTitle: "{l s='Review and merge file' mod='coreupdater'|escape:'javascript'}"
  };

  {literal}
  (function() {
    var savedMergeSelections = {};
    var savedMergeFiles = {};
    var currentPreview = null;
    var currentPreviewFile = null;
    var activeChunkId = null;
    var currentExplicitSelections = {};

    var escapeHtml = function(str) {
      return (str || '').replace(/[&<>]/g, function(c) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;'}[c];
      });
    };

    var decodeBase64 = function(str) {
      if (!str) {
        return '';
      }
      try {
        return decodeURIComponent(escape(window.atob(str)));
      } catch (e) {
        return window.atob(str);
      }
    };

    var encodeBase64 = function(str) {
      try {
        return window.btoa(unescape(encodeURIComponent(str)));
      } catch (e) {
        return window.btoa(str);
      }
    };

    var getSummaryText = function(choiceCount, conflictCount) {
      return mergeTexts.summaryTemplate
        .replace('%s', choiceCount)
        .replace('%s', conflictCount);
    };

    var getChoiceStateInfo = function(state) {
      switch (state) {
        case 'local':
          return {title: mergeTexts.localOnly, badge: 'badge-warning'};
        case 'incoming':
          return {title: mergeTexts.incomingOnly, badge: 'badge-success'};
        case 'same':
          return {title: mergeTexts.autoMerged, badge: 'badge-info'};
        default:
          return {title: mergeTexts.conflict, badge: 'badge-danger'};
      }
    };

    var renderDiff = function(diff) {
      var lines = diff.split('\n');
      if (lines.length >= 2 && lines[0].indexOf('--- ') === 0 && lines[1].indexOf('+++ ') === 0) {
        lines = lines.slice(2);
      }

      return lines.map(function(line) {
        var cls = '';
        if (line.indexOf('+++ ') === 0 || line.indexOf('--- ') === 0 || line.indexOf('@@') === 0) {
          cls = 'diff-hunk';
        } else if (line.indexOf('+') === 0) {
          cls = 'diff-add';
        } else if (line.indexOf('-') === 0) {
          cls = 'diff-del';
        }
        return '<span class="' + cls + '">' + escapeHtml(line) + '</span>';
      }).join('\n');
    };

    var splitPreservingLines = function(content) {
      if (!content) {
        return [];
      }
      return content.match(/[^\n]*\n|[^\n]+/g) || [];
    };

    var trimContext = function(content, keepEnd) {
      var lines = splitPreservingLines(content);
      var limit = 8;
      if (lines.length <= limit) {
        return content;
      }
      if (keepEnd) {
        return "...\n" + lines.slice(lines.length - limit).join('');
      }
      return lines.slice(0, limit).join('') + "...\n";
    };

    var setActiveChunk = function(chunkId) {
      activeChunkId = chunkId;
      $('#file-merge-chunks .merge-chunk').removeClass('active');
      $('#file-merge-chunks .merge-chunk[data-chunk-id="' + chunkId + '"]').addClass('active');
      $('#file-merge-result .merge-preview-choice').removeClass('active');
      $('#file-merge-result .merge-preview-choice[data-chunk="' + chunkId + '"]').addClass('active');
    };

    var getCurrentSelections = function() {
      return $.extend({}, currentExplicitSelections);
    };

    var requiresSelection = function(chunk) {
      return chunk && chunk.type === 'choice' && chunk.state !== 'same';
    };

    var hasPendingSelections = function(preview, selections) {
      var pending = false;
      (preview.chunks || []).forEach(function(chunk) {
        if (requiresSelection(chunk) && !Object.prototype.hasOwnProperty.call(selections, chunk.id)) {
          pending = true;
        }
      });
      return pending;
    };

    var buildMergedContent = function(preview, selections) {
      var content = '';
      (preview.chunks || []).forEach(function(chunk) {
        if (chunk.type === 'context') {
          content += decodeBase64(chunk.content);
          return;
        }

        if (chunk.state === 'same') {
          content += decodeBase64(chunk.content || chunk.incoming);
          return;
        }

        var choice = selections[chunk.id] || chunk.defaultChoice || 'incoming';
        content += decodeBase64(chunk[choice] || '');
      });

      var lineEnding = decodeBase64(preview.lineEnding || '');
      if (lineEnding && lineEnding !== '\n') {
        content = content.replace(/\n/g, lineEnding);
      }
      return content;
    };

    var syncVariantSelection = function() {
      $('#file-merge-chunks .merge-variant').removeClass('selected');
      var selections = getCurrentSelections();
      $('#file-merge-chunks .merge-chunk').each(function() {
        var chunkId = $(this).data('chunk-id');
        if (Object.prototype.hasOwnProperty.call(selections, chunkId)) {
          $('#file-merge-chunks .merge-variant[data-chunk="' + chunkId + '"][data-choice="' + selections[chunkId] + '"]').addClass('selected');
        }
      });
    };

    var updateMergeResult = function() {
      if (!currentPreview) {
        return;
      }
      var selections = getCurrentSelections();
      var html = [];
      (currentPreview.chunks || []).forEach(function(chunk) {
        if (chunk.type === 'context') {
          html.push('<span class="merge-preview-context">' + escapeHtml(decodeBase64(chunk.content)) + '</span>');
          return;
        }

        var content = '';
        var extraClass = '';
        if (chunk.state === 'same') {
          content = decodeBase64(chunk.content || chunk.incoming);
          extraClass = ' merge-preview-auto';
        } else {
          if (Object.prototype.hasOwnProperty.call(selections, chunk.id)) {
            var choice = selections[chunk.id];
            content = decodeBase64(chunk[choice] || '');
            extraClass = ' merge-preview-' + choice;
          } else {
            content = mergeTexts.pendingChoice;
            extraClass = ' merge-preview-pending';
          }
        }

        html.push(
          '<span class="merge-preview-choice' + extraClass + (activeChunkId === chunk.id ? ' active' : '') + '" data-chunk="' + chunk.id + '">' +
          escapeHtml(content) +
          '</span>'
        );
      });
      $('#file-merge-result').html(html.join(''));
      syncVariantSelection();
      if (activeChunkId) {
        $('#file-merge-result .merge-preview-choice[data-chunk="' + activeChunkId + '"]').addClass('active');
      }
      updateMergeSaveState();
    };

    var updateSavedStateBadge = function(file) {
      $('.merge-state').each(function() {
        var $badge = $(this);
        if ($badge.data('file') === file) {
          if (savedMergeFiles[file]) {
            $badge.show();
          } else {
            $badge.hide();
          }
        }
      });
    };

    var refreshMergeBlocks = function() {
      if (!currentPreview) {
        return;
      }
      var currentActive = activeChunkId;
      renderMergeChunks(currentPreview, getCurrentSelections());
      if (currentActive) {
        setActiveChunk(currentActive);
      }
    };

    var renderChunkCodePreview = function(chunk, before, after, selections) {
      var html = '<pre class="merge-inline-preview">';
      html += '<span class="merge-inline-context">' + escapeHtml(before) + '</span>';

      if (chunk.state === 'same') {
        html += '<span class="merge-inline-auto">' + escapeHtml(decodeBase64(chunk.content || chunk.incoming)) + '</span>';
      } else {
        if (Object.prototype.hasOwnProperty.call(selections, chunk.id)) {
          var choice = selections[chunk.id];
          html += '<span class="merge-inline-' + choice + '">' + escapeHtml(decodeBase64(chunk[choice] || '')) + '</span>';
        } else {
          html += '<span class="merge-inline-local">' + escapeHtml(decodeBase64(chunk.local || '')) + '</span>';
          html += '<span class="merge-inline-incoming">' + escapeHtml(decodeBase64(chunk.incoming || '')) + '</span>';
        }
      }

      html += '<span class="merge-inline-context">' + escapeHtml(after) + '</span>';
      html += '</pre>';
      return html;
    };

    var updateMergeSaveState = function() {
      if (!currentPreview) {
        $('#file-merge-save').prop('disabled', true).attr('title', '');
        return;
      }

      var pending = hasPendingSelections(currentPreview, getCurrentSelections());
      $('#file-merge-save')
        .prop('disabled', pending)
        .attr('title', pending ? mergeTexts.saveDisabled : '');
    };

    var renderMergeChunks = function(preview, selections) {
      var html = [];
      (preview.chunks || []).forEach(function(chunk, index) {
        if (chunk.type === 'context') {
          return;
        }

        var before = '';
        var after = '';
        if (index > 0 && preview.chunks[index - 1].type === 'context') {
          before = trimContext(decodeBase64(preview.chunks[index - 1].content), true);
        }
        if (index + 1 < preview.chunks.length && preview.chunks[index + 1].type === 'context') {
          after = trimContext(decodeBase64(preview.chunks[index + 1].content), false);
        }

        var info = getChoiceStateInfo(chunk.state);
        if (chunk.state === 'same') {
          html.push(
            '<div class="merge-chunk merge-chunk-auto" data-chunk-id="' + chunk.id + '">' +
            renderChunkCodePreview(chunk, before, after, selections) +
            '  <div class="merge-chunk-header">' +
            '    <span class="badge ' + info.badge + '">' + escapeHtml(info.title) + '</span>' +
            '  </div>' +
            '  <pre class="merge-auto-content">' + escapeHtml(decodeBase64(chunk.content || chunk.incoming)) + '</pre>' +
            '</div>'
          );
          return;
        }

        html.push(
          '<div class="merge-chunk merge-state-' + chunk.state + '" data-chunk-id="' + chunk.id + '">' +
          renderChunkCodePreview(chunk, before, after, selections) +
          '  <div class="merge-chunk-header">' +
          '    <span class="badge ' + info.badge + '">' + escapeHtml(info.title) + '</span>' +
          '  </div>' +
          '  <div class="merge-variants">' +
          '    <div class="merge-variant merge-variant-selectable merge-variant-local" data-chunk="' + chunk.id + '" data-choice="local">' +
          '      <div class="merge-variant-title">' + escapeHtml(mergeTexts.localVersion) + '</div>' +
          '      <pre>' + escapeHtml(decodeBase64(chunk.local)) + '</pre>' +
          '    </div>' +
          '    <div class="merge-variant merge-variant-selectable merge-variant-incoming" data-chunk="' + chunk.id + '" data-choice="incoming">' +
          '      <div class="merge-variant-title">' + escapeHtml(mergeTexts.incomingVersion) + '</div>' +
          '      <pre>' + escapeHtml(decodeBase64(chunk.incoming)) + '</pre>' +
          '    </div>' +
          '    <div class="merge-variant merge-variant-base" data-chunk="' + chunk.id + '" data-choice="base">' +
          '      <div class="merge-variant-title">' + escapeHtml(mergeTexts.installedCoreVersion) + '</div>' +
          '      <pre>' + escapeHtml(decodeBase64(chunk.base)) + '</pre>' +
          '    </div>' +
          '  </div>' +
          '</div>'
        );
      });
      $('#file-merge-chunks').html(html.join(''));
      $('#file-merge-chunks .merge-chunk').on('click', function(e) {
        setActiveChunk($(this).data('chunk-id'));
      });
      $('#file-merge-chunks .merge-variant').on('click', function() {
        var chunkId = $(this).data('chunk');
        var choice = $(this).data('choice');
        setActiveChunk(chunkId);
        if (choice === 'local' || choice === 'incoming') {
          currentExplicitSelections[chunkId] = choice;
          activeChunkId = chunkId;
          refreshMergeBlocks();
          updateMergeResult();
        } else {
          refreshMergeBlocks();
          updateMergeResult();
        }
      });
      $('#file-merge-result').off('click.mergePreview').on('click.mergePreview', '.merge-preview-choice', function() {
        setActiveChunk($(this).data('chunk'));
      });
      syncVariantSelection();
    };

    var openDiffPreview = function(file, response) {
      currentPreview = null;
      currentPreviewFile = file;
      $('#file-preview-title').text(mergeTexts.previewTitle);
      $('#file-preview-path').text(file);
      $('#file-preview-diff-container').show();
      $('#file-merge-container').hide();
      $('#file-merge-save').hide();
      $('#file-preview-diff').html(renderDiff(decodeBase64(response.diff || '')));
      $('#file-preview-modal').modal('show');
    };

    var openMergePreview = function(file, response) {
      currentPreview = response;
      currentPreviewFile = file;
      currentExplicitSelections = $.extend({}, savedMergeSelections[file] || {});
      $('#file-preview-title').text(mergeTexts.reviewTitle);
      $('#file-preview-path').text(file);
      $('#file-preview-diff-container').hide();
      $('#file-merge-container').show();
      $('#file-merge-save').show();
      $('#file-merge-summary').removeClass('alert-success').addClass('alert-info');
      $('#file-merge-summary').html(
        (response.fallback ? '<p>' + escapeHtml(mergeTexts.fallbackSummary) + '</p>' : '') +
        getSummaryText(response.choiceCount || 0, response.conflictCount || 0)
      );
      renderMergeChunks(response, savedMergeSelections[file] || {});
      activeChunkId = null;
      if (response.chunks && response.chunks.length) {
        response.chunks.some(function(chunk) {
          if (chunk.type === 'choice') {
            activeChunkId = chunk.id;
            return true;
          }
          return false;
        });
      }
      updateMergeResult();
      if (activeChunkId) {
        setActiveChunk(activeChunkId);
      }
      updateMergeSaveState();
      $('#file-preview-modal').modal('show');
    };

    var openFilePreview = function(file) {
      coreUpdater.preview(compareProcessId, file).then(function(response) {
        if (response.mode === 'merge') {
          openMergePreview(file, response);
        } else {
          openDiffPreview(file, response);
        }
      }).catch(function(err) {
        var message = err && err.message ? err.message : err;
        if (err && err.details) {
          message += '\n\n' + err.details;
        }
        alert(message);
      });
    };

    var applyChoiceToAll = function(choice) {
      $('#file-merge-chunks .merge-variant[data-choice="' + choice + '"]').each(function() {
        currentExplicitSelections[$(this).data('chunk')] = choice;
      });
      refreshMergeBlocks();
      updateMergeResult();
    };

    $('.review-file, .preview-file').on('click', function(e) {
      e.preventDefault();
      openFilePreview($(this).data('file'));
    });

    $('#merge-select-local').on('click', function() {
      applyChoiceToAll('local');
    });

    $('#merge-select-incoming').on('click', function() {
      applyChoiceToAll('incoming');
    });

    $('#file-merge-save').on('click', function() {
      if (!currentPreview || !currentPreviewFile) {
        return;
      }
      var selections = getCurrentSelections();
      savedMergeSelections[currentPreviewFile] = selections;
      savedMergeFiles[currentPreviewFile] = encodeBase64(buildMergedContent(currentPreview, selections));
      updateSavedStateBadge(currentPreviewFile);
      $('#file-merge-summary').removeClass('alert-info').addClass('alert-success').html(escapeHtml(mergeTexts.mergeSaved));
      $('#file-preview-modal').modal('hide');
    });

    $('#update-button').on('click', function() {
      var ignored = [];
      var mergedFiles = {};

      $('.ignore-file:checked').each(function() {
        ignored.push($(this).data('file'));
      });

      $.each(savedMergeFiles, function(file, content) {
        if (ignored.indexOf(file) === -1) {
          mergedFiles[file] = content;
        }
      });

      coreUpdater.update(compareProcessId, ignored, mergedFiles);
    });
  })();
  {/literal}
</script>
