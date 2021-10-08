/**
 * Copyright (C) 2018-2019 thirty bees
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
 * @copyright 2018-2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

window.initializeCoreUpdater = function(translations) {

  var executeAjax = function(url, payload) {
      return new Promise(function (resolve, reject) {
          $.ajax({
              url: url,
              type: 'POST',
              data: payload,
              dataType: 'json'
          }).then(
              function (response) {
                  if (!response) {
                      reject(createError("Empty response", ""));
                  } else {
                      if (response.success) {
                          resolve(response.data);
                      } else {
                          if (response.error && response.error.message && response.error.details) {
                              reject(createError(response.error.message, response.error.details));
                          } else {
                              reject(createError("Unknown error", ""));
                          }
                      }
                  }
              },
              function(response, error, details) {
                  if (response) {
                      console.error(error, details);
                      console.error('Response: ', response);
                      if (response.responseText) {
                          details = details + "\nResponse:\n" + response.responseText;
                      }
                  }
                  if (response &&
                      response.responseJSON &&
                      (typeof response.responseJSON.success !== 'undefined') &&
                      ! response.responseJSON.success &&
                      (typeof response.responseJSON.error !== 'undefined')
                  ) {
                    var err = response.responseJSON.error;
                    reject(createError(err.message, err.details));
                  } else {
                    if (error) {
                      reject(createError("Ajax request failed: " + error, details));
                    } else {
                      reject(createError("Ajax request failed", details));
                    }
                  }
                }
          );
      });
  };

  /**
   * @return Promise
   * @param action
   * @param params
   */
  var executeAction = function(action, params={}) {
    var payload = {
      ajax: 1,
      action: action,
      token: window.token
    };
    for (var property in params) {
      if (params.hasOwnProperty(property)) {
        payload[property] = params[property];
      }
    }
    return executeAjax(window.currentIndex, payload);
  };

  var createError = function(error, details) {
    var e = new Error(error);
    // noinspection JSUndefinedPropertyAssignment
    e.details = details;
    return e;
  };

  var displayError = function(error) {
    console.error(error);
    var message = error.message || error;
    var details = error.details || '';
    $('#panel-progress').hide();
    $('#error-block #error-message').text(message);
    $('#error-block #error-details').text(details);
    $('#error-block').show();
  };

  var hideError = function() {
    $('#error-block').hide();
  };

  var setProgressBar = function(progress, text) {
    var percentage = (Math.round(progress * 10000) / 100) + "%";
    var $compareProgressBar = $('#progress-bar');
    $compareProgressBar.text(percentage);
    $compareProgressBar.css("width", percentage);
    $('#progress-bar-text').text(text);
  };

  var incrementProcess = function(action, process, onSuccess) {
    setProgressBar(process.progress, process.step);
    executeAction(action, { processId: process.id })
      .then(function(newProcess) {
          if (newProcess.ajax) {
              return executeAjax(newProcess.ajax, { processId: process.id })
                  .then(_ => newProcess);
          } else {
              return newProcess;
          }
      })
      .then(function(newProcess) {
        switch (newProcess.status) {
          case 'IN_PROGRESS':
            incrementProcess(action, newProcess, onSuccess);
            return;
          case 'DONE':
            setProgressBar(1.0, newProcess.step);
            onSuccess(newProcess.result);
            return;
          case 'FAILED':
            throw createError(newProcess.error, newProcess.details);
        }
      })
      .catch(displayError);
  };

  var initProgress = function(header, description) {
    var $progress = $('#panel-progress');
    var $result = $('#result');
    var $header = $('#progress-header');
    var $description = $('#progress-description');
    hideError();
    setProgressBar(0, translations.INITIALIZING);
    $result.hide();
    $header.html(header);
    $description.html(description);
    $progress.show();
  };

  var endProgress = function(result) {
    var $progress = $('#panel-progress');
    var $result = $('#result');
    $progress.hide();
    $result.html(result.html);
    $result.show();
  };

  var compare = function(process) {
    initProgress(translations.CHECKING_YOUR_INSTALLATION, translations.CHECKING_DESCRIPTION);
    incrementProcess('COMPARE', process, endProgress);
  };

  var runUpdate = function(process) {
    incrementProcess('UPDATE', process, endProgress);
  };

  var update = function(compareProcessId) {
    initProgress(translations.UPDATE, translations.UPDATE_DESCRIPTION);
    executeAction('INIT_UPDATE', { compareProcessId: compareProcessId })
        .then(runUpdate)
        .catch(displayError);
  };

  var checkDatabase = function() {
    document.getElementById('db-changes').className = 'status-running';
    executeAction('GET_DATABASE_DIFFERENCES')
      .then(getDatabaseDifferencesSuccess)
      .catch(checkDatabaseError);
  };

  var checkDatabaseError = function(error) {
    document.getElementById('db-changes').className = 'status-error';
    displayError(error);
  };

  var getDatabaseDifferencesSuccess = function(differences) {
    if (differences && differences.length > 0) {
      var $list = $('#db-changes-list');
      $list.empty();
      differences.forEach(function(diff) {
        var severityInfo = getSeverityInfo(diff.severity);
        $list.append($(
          '<tr class="db-change">' +
          '  <td>' +
          '    <span class="badge '+severityInfo.badge+'" title="'+severityInfo.tooltip+'">'+severityInfo.title+'</span>' +
          '  </td>' +
          '  <td>' +
          '    '+ (diff.destructive ? '<span class="badge badge-danger" title="Fix is potentially dangerous operation that may result in data loss">dangerous</span>' : '') +
          '  </td>' +
          '  <td>' +
          '    '+ replaceTags(diff.description) +
          '  </td>' +
          '  <td class="text-right">' +
          '    '+'<a href="#" data-id="'+diff.id+'" class="btn btn-default"><i class="icon icon-gears"></i> Apply fix</a>'+
          '  </td>' +
          '</tr>'
        ));
      });
      $('#db-changes-list .badge').tooltip();
      $('#db-changes-list .btn').on('click', applyFixHandler);
      document.getElementById('db-changes').className = 'status-changes';
    } else {
      document.getElementById('db-changes').className = 'status-no-changes';
    }
  };

  var applyFixHandler = function(e) {
    e.preventDefault();
    e.stopPropagation();
    var id = $(this).data('id');
    if (id) {
      applyDatabaseFix([ id ]);
    }
  };

  var applyDatabaseFix = function(ids) {
    $('.changes .table').addClass('loading');
    executeAction('APPLY_DATABASE_FIX', { ids: ids })
      .then(getDatabaseDifferencesSuccess)
      .then(function() {
        $('.changes .table').removeClass('loading');
      })
      .catch(checkDatabaseError);
  };

  var getSeverityInfo = function(severity) {
    switch (severity) {
      case 0:
        return {
          badge: 'module-badge-bought',
          title: 'informational',
          tooltip: 'You should ignore this difference'
        };
      case 1:
        return {
          badge: 'badge-info',
          title: 'recommended',
          tooltip: 'This is not a critical issue, but we still recommend you to fix this',
        };
      case 2:
        return {
          badge: 'badge-danger',
          title: 'critical',
          tooltip: 'This is critical issue and you should fix it immediately. Failure to do so might result in system not working correctly',
        };
    }
  };

  var replaceTags = function(str) {
    var output = str;
    var open = new RegExp('\\[[0-9]+\\]', 'g');
    var close = new RegExp('\\[\\/[0-9]+\\]', 'g');
    output = output.replace(open, "<strong>");
    output = output.replace(close, "</strong>");
    return output;
  };

  /**
   * Public API
   */
  return {
    compare: compare,
    update: update,
    checkDatabase: checkDatabase,
  }
};

