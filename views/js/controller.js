/**
 * Copyright (C) 2018 thirty bees
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
 * @copyright 2018 thirty bees
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
      });
    },
    error: function(xhr, status, error) {
      console.log('api.thirtybees.com request failed with status \'' + status
        + '\' and \'' + xhr.state() + '\'.'
      );
    },
  });
}
