<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2017 by Laurent Declercq <l.declercq@nuxwin.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/***********************************************************************************************************************
 * Functions
 */

/**
 * Genrate statistics entry for the given user
 *
 * @param iMSCP_pTemplate $tpl Template engine instance
 * @param int $adminId User unique identifier
 * @return void
 */
function _generateUserStatistics($tpl, $adminId)
{
    list(
        $adminName, , $web, $ftp, $smtp, $pop3, $trafficUsageBytes, $diskspaceUsageBytes
        ) = shared_getCustomerStats($adminId);

    list(
        $usub_current, $usub_max, $uals_current, $uals_max, $umail_current, $umail_max, $uftp_current, $uftp_max,
        $usql_db_current, $usql_db_max, $usql_user_current, $usql_user_max, $trafficMaxMebimytes, $diskspaceMaxMebibytes
        ) = shared_getCustomerProps($adminId);

    $trafficLimitBytes = $trafficMaxMebimytes * 1048576;
    $diskspaceLimitBytes = $diskspaceMaxMebibytes * 1048576;
    $trafficUsagePercent = make_usage_vals($trafficUsageBytes, $trafficLimitBytes);
    $diskspaceUsagePercent = make_usage_vals($diskspaceUsageBytes, $diskspaceLimitBytes);

    $tpl->assign([
        'USER_NAME'     => tohtml(decode_idna($adminName)),
        'USER_ID'       => tohtml($adminId),
        'TRAFF_PERCENT' => tohtml($trafficUsagePercent),
        'TRAFF_MSG'     => ($trafficLimitBytes)
            ? tohtml(tr('%s / %s', bytesHuman($trafficUsageBytes), bytesHuman($trafficLimitBytes)))
            : tohtml(tr('%s / ∞', bytesHuman($trafficUsageBytes))),
        'DISK_PERCENT'  => tohtml($diskspaceUsagePercent),
        'DISK_MSG'      => ($diskspaceLimitBytes)
            ? tohtml(tr('%s / %s', bytesHuman($diskspaceUsageBytes), bytesHuman($diskspaceLimitBytes)))
            : tohtml(tr('%s / ∞', bytesHuman($diskspaceUsageBytes))),
        'WEB'           => tohtml(bytesHuman($web)),
        'FTP'           => tohtml(bytesHuman($ftp)),
        'SMTP'          => tohtml(bytesHuman($smtp)),
        'POP3'          => tohtml(bytesHuman($pop3)),
        'SUB_MSG'       => tohtml(tr('%s / %s', $usub_current, translate_limit_value($usub_max))),
        'ALS_MSG'       => tohtml(tr('%s / %s', $uals_current, translate_limit_value($uals_max))),
        'MAIL_MSG'      => tohtml(tr('%s / %s', $umail_current, translate_limit_value($umail_max))),
        'FTP_MSG'       => tohtml(tr('%s / %s', $uftp_current, translate_limit_value($uftp_max))),
        'SQL_DB_MSG'    => tohtml(tr('%s / %s', $usql_db_current, translate_limit_value($usql_db_max))),
        'SQL_USER_MSG'  => tohtml(tr('%s / %s', $usql_user_current, translate_limit_value($usql_user_max)))
    ]);
}

/**
 * Generates page
 *
 * @param iMSCP_pTemplate $tpl Template engine instance
 * @param int $resellerId Reseller unique identifier
 * @return void
 */
function generatePage($tpl, $resellerId)
{
    $stmt = exec_query('SELECT admin_id FROM admin WHERE created_by = ?', $resellerId);

    if ($stmt->rowCount()) {
        while ($row = $stmt->fetchRow()) {
            _generateUserStatistics($tpl, $row['admin_id']);
            $tpl->parse('RESELLER_USER_STATISTICS_BLOCK', '.reseller_user_statistics_block');
        }
    } else {
        $tpl->assign('RESELLER_USER_STATISTICS_BLOCK', '');
    }
}

/***********************************************************************************************************************
 * Main
 */

require 'imscp-lib.php';

check_login('admin');
iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onAdminScriptStart);

if (isset($_GET['reseller_id'])) {
    $resellerId = intval($_GET['reseller_id']);
    $_SESSION['stats_reseller_id'] = $resellerId;
} elseif (isset($_SESSION['stats_reseller_id'])) {
    redirectTo('reseller_user_statistics.php?reseller_id=' . $_SESSION['stats_reseller_id']);
    exit;
} else {
    showBadRequestErrorPage();
    exit;
}

$tpl = new iMSCP_pTemplate();
$tpl->define_dynamic([
    'layout'                         => 'shared/layouts/ui.tpl',
    'page'                           => 'admin/reseller_user_statistics.tpl',
    'page_message'                   => 'layout',
    'reseller_user_statistics_block' => 'page'
]);
$tpl->assign([
    'TR_PAGE_TITLE'             => tohtml(tr('Admin / Statistics / Reseller Statistics / User Statistics')),
    'TR_USERNAME'               => tohtml(tr('User')),
    'TR_TRAFF'                  => tohtml(tr('Monthly traffic usage')),
    'TR_DISK'                   => tohtml(tr('Disk usage')),
    'TR_WEB'                    => tohtml(tr('HTTP traffic')),
    'TR_FTP_TRAFF'              => tohtml(tr('FTP traffic')),
    'TR_SMTP'                   => tohtml(tr('SMTP traffic')),
    'TR_POP3'                   => tohtml(tr('POP3/IMAP traffic')),
    'TR_SUBDOMAIN'              => tohtml(tr('Subdomains')),
    'TR_ALIAS'                  => tohtml(tr('Domain aliases')),
    'TR_MAIL'                   => tohtml(tr('Mail accounts')),
    'TR_FTP'                    => tohtml(tr('FTP accounts')),
    'TR_SQL_DB'                 => tohtml(tr('SQL databases')),
    'TR_SQL_USER'               => tohtml(tr('SQL users')),
    'TR_DETAILED_STATS_TOOLTIP' => tohtml(tr('Show detailed statistics for this user'), 'htmlAttr')
]);

iMSCP_Events_Aggregator::getInstance()->registerListener('onGetJsTranslations', function ($e) {
    /** @var $e \iMSCP_Events_Event */
    $e->getParam('translations')->core['dataTable'] = getDataTablesPluginTranslations(false);
});

generateNavigation($tpl);
generatePage($tpl, $resellerId);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onAdminScriptEnd, ['templateEngine' => $tpl]);
$tpl->prnt();

unsetMessages();
