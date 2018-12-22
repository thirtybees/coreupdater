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
  if (channel === 'tags') {
    versionSelect.append('<option>' + 'tag 1' + '</option>');
    versionSelect.append('<option>' + 'tag 2' + '</option>');
    versionSelect.append('<option>' + 'tag 3' + '</option>');
  } else if (channel === 'branches') {
    versionSelect.append('<option>' + 'branch 1' + '</option>');
    versionSelect.append('<option>' + 'branch 2' + '</option>');
    versionSelect.append('<option>' + 'branch 3' + '</option>');
  }
}
