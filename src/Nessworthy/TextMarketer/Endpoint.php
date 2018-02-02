<?php declare(strict_types=1);

namespace Nessworthy\TextMarketer;

use Nessworthy\TextMarketer\Account\AccountInformation;
use Nessworthy\TextMarketer\Account\Command\CreateSubAccount;
use Nessworthy\TextMarketer\Account\Command\UpdateAccountInformation;
use Nessworthy\TextMarketer\Authentication\Authentication;
use Nessworthy\TextMarketer\Credit\TransferReport;
use Nessworthy\TextMarketer\DeliveryReport\DateRange;
use Nessworthy\TextMarketer\DeliveryReport\DeliveryReportCollection;
use Nessworthy\TextMarketer\Keyword\KeywordAvailability;
use Nessworthy\TextMarketer\Message\MessageDeliveryReport;
use Nessworthy\TextMarketer\Message\Part\PhoneNumberCollection;
use Nessworthy\TextMarketer\Message\Command\SendMessage;
use Nessworthy\TextMarketer\SendGroup\AddNumbersToGroupReport;
use Nessworthy\TextMarketer\SendGroup\SendGroup;
use Nessworthy\TextMarketer\SendGroup\SendGroupSummaryCollection;

/**
 * Interface Endpoint
 *
 * The API available to you to interact with Text Marketer's API.
 *
 * Note - Some of these methods expect an account ID.
 * This can be found in the page footer at https://messagebox.textmarketer.co.uk.
 *
 * @package Nessworthy\TextMarketer\Endpoint
 */
interface Endpoint
{
    /**
     * Send an SMS message.
     * @param SendMessage $message
     * @return MessageDeliveryReport
     */
    public function sendMessage(SendMessage $message): MessageDeliveryReport;

    /**
     * Schedule an SMS message to be sent out at a given date.
     * @param SendMessage $message
     * @param \DateTimeImmutable $deliveryTime
     * @return MessageDeliveryReport
     */
    public function sendScheduledMessage(SendMessage $message, \DateTimeImmutable $deliveryTime): MessageDeliveryReport;

    /**
     * Delete a scheduled SMS message.
     * Note - this will fail if the message has already been sent.
     * @param string $scheduleId
     */
    public function deleteScheduledMessage(string $scheduleId): void;

    /**
     * Retrieve information for the account in use.
     * Warning - this endpoint returns passwords!
     * @return AccountInformation
     */
    public function getAccountInformation(): AccountInformation;

    /**
     * Retrieve information for a given account or sub account.
     * @param string $accountId
     * @return AccountInformation
     */
    public function getAccountInformationForAccountId(string $accountId): AccountInformation;

    /**
     * Update account information for the account in use.
     * @param UpdateAccountInformation $newAccountInformation
     * @return AccountInformation
     */
    public function updateAccountInformation(UpdateAccountInformation $newAccountInformation): AccountInformation;

    /**
     * Create a new sub-account for the account in use.
     * Note - This feature is disabled by default until you request Text Marketer to enable it.
     * @param CreateSubAccount $subAccountDetails
     * @return AccountInformation
     */
    public function createSubAccount(CreateSubAccount $subAccountDetails): AccountInformation;

    /**
     * Retrieves the amount of credits the account in use currently has available.
     * @return int
     */
    public function getCreditCount(): int;

    /**
     * Transfer credits from the account in use to another account using its account ID.
     * @param string $destinationAccountId
     * @return TransferReport
     */
    public function transferCreditsToAccountById(string $destinationAccountId): TransferReport;

    /**
     * Transfer credits from the account in use to another account using its username and password.
     * @param Authentication $destinationAccountDetails
     * @return TransferReport
     */
    public function transferCreditsToAccountByCredentials(Authentication $destinationAccountDetails): TransferReport;

    /**
     * Retrieve the full list of delivery reports available for the account in use.
     * @return DeliveryReportCollection
     */
    public function getDeliveryReportList(): DeliveryReportCollection;

    /**
     * Retrieve a filtered list of reports by name.
     * @param string $reportName
     * @return DeliveryReportCollection
     */
    public function getDeliveryReportListByName(string $reportName): DeliveryReportCollection;

    /**
     * Retrieve a filtered list of reports by name between the specified date range.
     * @param string $reportName
     * @param DateRange $createdBetween
     * @return DeliveryReportCollection
     */
    public function getDeliveryReportListByNameAndDateRange(string $reportName, DateRange $createdBetween): DeliveryReportCollection;

    /**
     * Retrieve a filtered list of reports by name and tag.
     * @param string $reportName
     * @param string $tag
     * @return DeliveryReportCollection
     */
    public function getDeliveryReportListByNameAndTag(string $reportName, string $tag): DeliveryReportCollection;

    /**
     * Retrieve a filtered list of reports by name and tag between the specified date range.
     * @param string $reportName
     * @param string $tag
     * @param DateRange $createdBetween
     * @return DeliveryReportCollection
     */
    public function getDeliveryReportListByNameTagAndDateRange(string $reportName, string $tag, DateRange $createdBetween): DeliveryReportCollection;

    /**
     * Retrieve the availability information of a given keyword.
     * @param string $keyword
     * @return KeywordAvailability
     */
    public function checkKeywordAvailability(string $keyword): KeywordAvailability;

    /**
     * Retrieve the full list of groups for the account in use.
     * @return SendGroupSummaryCollection
     */
    public function getGroupsList(): SendGroupSummaryCollection;

    /**
     * Add one or more numbers to a group.
     * @param string $groupNameOrId
     * @param PhoneNumberCollection $numbers
     * @return AddNumbersToGroupReport
     */
    public function addNumbersToGroup(string $groupNameOrId, PhoneNumberCollection $numbers): AddNumbersToGroupReport;

    /**
     * Create a new group.
     * @param string $groupName
     * @return SendGroup
     */
    public function createGroup(string $groupName): SendGroup;

    /**
     * Retrieve information for a given group.
     * @param string $groupNameOrId
     * @return SendGroup
     */
    public function getGroupInformation(string $groupNameOrId): SendGroup;
}
