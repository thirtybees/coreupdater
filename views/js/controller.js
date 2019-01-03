/**
 * Copyright (C) 2018-2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2018-2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

/*
 * Upgrade panel.
 */
var coreUpdaterParameters;

$(document).ready(function () {
  coreUpdaterParameters = JSON.parse($('input[name=CORE_UPDATER_PARAMETERS]').val());

  channelChange();
  $('#CORE_UPDATER_CHANNEL').on('change', channelChange);

  $('#CORE_UPDATER_VERSION').on('change', versionChange);

  if (document.getElementById('configuration_fieldset_comparepanel')) {
    processCompare();
  }
});

function channelChange() {
  let channel = $('#CORE_UPDATER_CHANNEL option:selected').val();
  let versionSelect = $('#CORE_UPDATER_VERSION');

  if ( ! channel || ! versionSelect.length) {
    return;
  }

  versionSelect.empty();
  $.ajax({
    url: coreUpdaterParameters.apiUrl,
    type: 'POST',
    data: {'list': channel},
    dataType: 'json',
    success: function(data, status, xhr) {
      data.forEach(function(version) {
          versionSelect.append('<option>'+version+'</option>');
          if (version === coreUpdaterParameters.selectedVersion) {
            versionSelect.val(coreUpdaterParameters.selectedVersion);
          }
      });
      $('#conf_id_CORE_UPDATER_VERSION')
        .find('.help-block')
        .parent()
        .slideUp(200);
    },
    error: function(xhr, status, error) {
      let helpText = $('#conf_id_CORE_UPDATER_VERSION').find('.help-block');
      helpText.html(coreUpdaterParameters.errorRetrieval);
      helpText.css('color', 'red');
      console.log('Request to '+coreUpdaterParameters.apiUrl
                  +' failed with status \''+xhr.state()+'\'.');
    },
  });
}

function versionChange() {
  if ($(this).val() === coreUpdaterParameters.selectedVersion) {
    $('#configuration_fieldset_comparepanel').slideDown(1000);
  } else {
    $('#configuration_fieldset_comparepanel').slideUp(1000);
  }
}

function processCompare() {
  let url = document.URL+'&action=processCompare&ajax=1';

  $.ajax({
    url: url,
    type: 'POST',
    data: {'compareVersion': coreUpdaterParameters.selectedVersion},
    dataType: 'json',
    success: function(data, status, xhr) {
      logField = $('textarea[name=CORE_UPDATER_PROCESSING]')[0];
      infoList = data['informations'];
      infoListLength = infoList.length;

      for (i = 0; i < infoListLength; i++) {
        logField.value += "\n";
        if (data['error'] && i === infoListLength - 1) {
          logField.value += "ERROR: ";
          $('#conf_id_CORE_UPDATER_PROCESSING')
            .children('label')
            .html(coreUpdaterParameters.errorProcessing)
            .css('color', 'red');
        }
        logField.value += data['informations'][i];
      }

      logField.scrollTop = logField.scrollHeight;

      if (data['done'] === false) {
        processCompare();
      }
    },
    error: function(xhr, status, error) {
      $('#configuration_fieldset_comparepanel')
        .children('.form-wrapper')
        .html(coreUpdaterParameters.errorRetrieval)
        .css('color', 'red');
      console.log('Request to '+url+' failed with status \''+xhr.state()+'\'.');
    }
  });
}
