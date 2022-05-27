{**
 * Copyright (C) 2021 thirty bees
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
<div class="row">
    <div class="col-lg-12">
        <div class="panel" id="panel-progress">
            <div class="panel-heading">{l s='Select version' mod='coreupdater'}</div>
            <form method="post" class="form-horizontal">
                <div class="row">
                    <div class="form-group">
                        <label class="control-label col-lg-3" for="version_type">{l s='Version type' mod='coreupdater'}</label>
                        <div class="col-lg-9">
                            <select id="version_type" name="version_type" class="form-control fixed-width-xxl">
                                <option value="release" selected>{l s='Official releases' mod='coreupdater'}</option>
                                <option value="branch">{l s='Development branches' mod='coreupdater'}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="releases">
                        <label class="control-label col-lg-3" for="release">{l s='Releases' mod='coreupdater'}</label>
                        <div class="col-lg-9">
                            <select name="release" class="form-control fixed-width-xxl">
                                {foreach $targets.releases as $item}
                                    <option value="{$item.revision}">{$item.name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="branches" style="display:none">
                        <label class="control-label col-lg-3" for="branch">{l s='Development branches' mod='coreupdater'}</label>
                        <div class="col-lg-9">
                            <select name="branch" class="form-control fixed-width-xxl">
                                {foreach $targets.branches as $item}
                                    <option value="{$item.revision}">{$item.name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary col-lg-offset-3" name="submitUpdate">{l s='Update' mod='coreupdater'}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="application/javascript">
    $('#version_type').change(function () {
        if ($(this).val() === 'release') {
            $('#releases').show();
            $('#branches').hide();
        } else {
            $('#releases').hide();
            $('#branches').show();
        }
    });
</script>