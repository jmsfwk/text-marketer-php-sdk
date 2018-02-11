<?php declare(strict_types=1);

namespace Nessworthy\TextMarketer\Endpoint;

use GuzzleHttp\Client;
use Nessworthy\TextMarketer\Account\AccountInformation;
use Nessworthy\TextMarketer\Account\CreateSubAccount;
use Nessworthy\TextMarketer\Account\UpdateAccountInformation;
use Nessworthy\TextMarketer\Authentication\Authentication;
use Nessworthy\TextMarketer\Credit\TransferReport;
use Nessworthy\TextMarketer\Keyword\KeywordAvailability;
use Nessworthy\TextMarketer\Message\InvalidMessageException;
use Nessworthy\TextMarketer\Message\Part\PhoneNumberCollection;
use Nessworthy\TextMarketer\Message\SendMessage;
use Nessworthy\TextMarketer\Message\MessageDeliveryReport;
use Nessworthy\TextMarketer\SendGroup\AddNumbersToGroupReport;
use Nessworthy\TextMarketer\SendGroup\SendGroup;
use Nessworthy\TextMarketer\SendGroup\SendGroupSummary;
use Nessworthy\TextMarketer\SendGroup\SendGroupSummaryCollection;

final class Sandbox implements MessageEndpoint, CreditEndpoint, KeywordEndpoint, AccountEndpoint, GroupEndpoint
{
    /**
     * @var Client
     */
    private $httpClient;
    /**
     * @var Authentication
     */
    private $authentication;

    public function __construct(Authentication $authentication, Client $httpClient = null)
    {
        if ($httpClient === null) {
            $httpClient = new Client();
        }

        $this->httpClient = $httpClient;
        $this->authentication = $authentication;
    }

    /**
     * @inheritDoc
     */
    public function sendMessage(SendMessage $message): MessageDeliveryReport
    {
        $response = $this->toDomDocument($this->sendPostRequest(
            $this->buildEndpointUri('sms'),
            $this->buildSendMessageRequestParameters($message)
        ));

        return $this->handleSendMessageResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function sendScheduledMessage(SendMessage $message, \DateTimeImmutable $deliveryTime): MessageDeliveryReport
    {
        $data = $this->buildSendMessageRequestParameters($message);
        $data['schedule'] = $deliveryTime->format('c');
        $response = $this->toDomDocument($this->sendPostRequest(
            $this->buildEndpointUri('sms'),
            $data
        ));

       return $this->handleSendMessageResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function deleteScheduledMessage(string $scheduleId): void
    {
        $response = $this->toDomDocument(
            $this->sendDeleteRequest(
                $this->buildEndpointUri('sms', $scheduleId)
            )
        );

        $this->handleDeleteScheduleMessageResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function getCreditCount(): int
    {
        $response = $this->toDomDocument(
            $this->sendGetRequest(
                $this->buildEndpointUri('credits')
            )
        );

        $this->handleAnyErrors($response);

        return (int) $this->getTagContentByName('credits', $response);
    }

    /**
     * @inheritDoc
     */
    public function transferCreditsToAccountById(int $quantity, string $destinationAccountId): TransferReport
    {
        $response = $this->toDomDocument(
            $this->sendPostRequest(
                $this->buildEndpointUri('credits'),
                ['quantity' => $quantity, 'target' => $destinationAccountId]
            )
        );

        $this->handleAnyErrors($response);

        $sourceBefore = (int) $this->getTagContentByName('source_credits_before', $response);
        $sourceAfter = (int) $this->getTagContentByName('source_credits_after', $response);
        $targetBefore = (int) $this->getTagContentByName('target_credits_before', $response);
        $targetAfter = (int) $this->getTagContentByName('target_credits_after', $response);

        return new TransferReport($sourceBefore, $sourceAfter, $targetBefore, $targetAfter);
    }

    /**
     * @inheritDoc
     */
    public function transferCreditsToAccountByCredentials(int $quantity, Authentication $destinationAccountDetails): TransferReport
    {
        // TODO: DRY!
        $response = $this->toDomDocument(
            $this->sendPostRequest(
                $this->buildEndpointUri('credits'),
                [
                    'quantity' => $quantity,
                    'target_username' => $destinationAccountDetails->getUserName(),
                    'target_password' => $destinationAccountDetails->getPassword()
                ]
            )
        );

        $this->handleAnyErrors($response);

        $sourceBefore = (int) $this->getTagContentByName('source_credits_before', $response);
        $sourceAfter = (int) $this->getTagContentByName('source_credits_after', $response);
        $targetBefore = (int) $this->getTagContentByName('target_credits_before', $response);
        $targetAfter = (int) $this->getTagContentByName('target_credits_after', $response);

        return new TransferReport($sourceBefore, $sourceAfter, $targetBefore, $targetAfter);
    }

    /**
     * @inheritDoc
     */
    public function checkKeywordAvailability(string $keyword): KeywordAvailability
    {
        $response = $this->toDomDocument(
            $this->sendGetRequest($this->buildEndpointUri('keywords', $keyword))
        );

        $this->handleAnyErrors($response);

        $isAvailable = $this->getTagContentByName('available', $response) !== 'false';
        $isRecycled = $this->getTagContentByName('recycle', $response) !== 'false';

        return new KeywordAvailability($isAvailable, $isRecycled);
    }

    /**
     * @inheritDoc
     */
    public function getAccountInformation(): AccountInformation {
        return $this->fetchAccountInformation();
    }

    /**
     * @inheritDoc
     */
    public function getAccountInformationForAccountId(string $accountId): AccountInformation {
        return $this->fetchAccountInformation($accountId);
    }

    /**
     * @inheritDoc
     */
    public function updateAccountInformation(UpdateAccountInformation $newAccountInformation): AccountInformation {
        $response = $this->toDomDocument(
            $this->sendPostRequest(
                $this->buildEndpointUri('account'),
                array_filter([
                    'account_api_password' => $newAccountInformation->getApiPassword(),
                    'account_api_username' => $newAccountInformation->getApiUserName(),
                    'account_password' => $newAccountInformation->getAccountPassword(),
                    'account_username' => $newAccountInformation->getAccountUserName(),
                    'company_name' => $newAccountInformation->getCompanyName(),
                    'notification_email' => $newAccountInformation->getNotificationEmail(),
                    'notification_mobile' => $newAccountInformation->getNotificationMobile(),
                ])
            )
        );

        return $this->handleAccountResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function createSubAccount(CreateSubAccount $subAccountDetails): AccountInformation {
        $response = $this->toDomDocument(
            $this->sendPutRequest(
                $this->buildEndpointUri('account', 'sub'),
                array_filter([
                    'account_password' => $subAccountDetails->getAccountUserName(),
                    'account_username' => $subAccountDetails->getAccountPassword(),
                    'company_name' => $subAccountDetails->getCompanyName(),
                    'notification_email' => $subAccountDetails->getNotificationEmail(),
                    'notification_mobile' => $subAccountDetails->getNotificationMobile(),
                    'override_pricing' => $subAccountDetails->enablePricingOverride() ? 'true' : 'false',
                    'promo_code' => $subAccountDetails->getPromoCode(),
                ])
            )
        );

        return $this->handleAccountResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function getGroupsList(): SendGroupSummaryCollection
    {
        $response = $this->toDomDocument($this->sendGetRequest($this->buildEndpointUri('groups')));

        $this->handleAnyErrors($response);

        $groups = [];

        $groupElements = $response->getElementsByTagName('group');

        /** @var \DOMNode $groupElement */
        foreach ($groupElements as $groupElement) {
            $groups[] = new SendGroupSummary(
                $groupElement->attributes->getNamedItem('id')->textContent,
                $groupElement->attributes->getNamedItem('name')->textContent,
                (int) $groupElement->attributes->getNamedItem('numbers')->textContent,
                $groupElement->attributes->getNamedItem('is_stop')->textContent !== 'false'
            );
        }

        return new SendGroupSummaryCollection(...$groups);
    }

    /**
     * @inheritDoc
     */
    public function addNumbersToGroup(string $groupNameOrId, PhoneNumberCollection $numbers): AddNumbersToGroupReport
    {
        $response = $this->toDomDocument(
            $this->sendPostRequest(
                $this->buildEndpointUri('groups', $groupNameOrId),
                implode(',', $numbers->asArray())
            )
        );

        $this->handleAnyErrors($response);

        $addedNumberNodes = $response->getElementsByTagName('added')->item(0)->childNodes;
        $stoppedNumberNodes = $response->getElementsByTagName('stopped')->item(0)->childNodes;
        $duplicateNumberNodes = $response->getElementsByTagName('duplicates')->item(0)->childNodes;

        $added = [];
        $stopped = [];
        $duplicates = [];

        /** @var \DOMNode $addedNumberNode */
        foreach($addedNumberNodes as $addedNumberNode) {
            $added[] = $addedNumberNode->textContent;
        }

        /** @var \DOMNode $stoppedNumberNode */
        foreach($stoppedNumberNodes as $stoppedNumberNode) {
            $added[] = $stoppedNumberNode->textContent;
        }

        /** @var \DOMNode $duplicateNumberNode */
        foreach($duplicateNumberNodes as $duplicateNumberNode) {
            $added[] = $duplicateNumberNode->textContent;
        }

        try {
            return new AddNumbersToGroupReport(
                new PhoneNumberCollection($added),
                new PhoneNumberCollection($stopped),
                new PhoneNumberCollection($duplicates)
            );
        } catch (InvalidMessageException $e) {
            throw new EndpointException(new EndpointError(-1, 'Could not parse returned numbers: ' . $e->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function createGroup(string $groupName): SendGroup
    {
        $response = $this->toDomDocument($this->sendPutRequest($this->buildEndpointUri('groups', $groupName)));

        return $this->handleGroupResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function getGroupInformation(string $groupNameOrId): SendGroup
    {
        $response = $this->toDomDocument($this->sendGetRequest($this->buildEndpointUri('groups', $groupNameOrId)));

        return $this->handleGroupResponse($response);
    }

    private function buildEndpointUri(string ...$components): string
    {
        return 'http://sandbox.textmarketer.biz/services/rest/'
            . implode(
                '/', array_map(
                    'urlencode',
                    $components
                )
            );
    }

    private function buildSendMessageRequestParameters(SendMessage $message): array
    {
        $return = [
            'message' => $message->getMessageText(),
            'to' => implode(',', $message->getMessageRecipients()),
            'originator' => $message->getMessageOriginator(),
        ];

        if ($message->hasTxtUsEmail()) {
            $return['email'] = $message->getTxtUsEmail();
        }

        if ($message->hasHourValidity()) {
            $return['validity'] = $message->getValidity();
        }

        $return['check_stop'] = $message->isCheckSTOPEnabled();

        return $return;
    }

    /**
     * @param \DomNodeList $errors
     * @return EndpointError[]
     */
    private function buildEndpointErrors($errors): array
    {
        $errorList = [];
        /** @var \DOMNode $error */
        foreach ($errors as $error) {
            $errorList[] = new EndpointError(
                (int) $error->attributes->getNamedItem('code')->textContent,
                $error->textContent
            );
        }
        return $errorList;
    }


    private function sendDeleteRequest(string $endpoint): string
    {
        return $this->sendAuthenticatedHttpRequest('delete', $endpoint);
    }

    private function sendPostRequest(string $endpoint, array $postData = []): string
    {
        return $this->sendAuthenticatedHttpRequest('post', $endpoint, ['form_params' => $postData]);
    }

    private function sendGetRequest(string $endpoint): string
    {
        return $this->sendAuthenticatedHttpRequest('get', $endpoint);
    }

    private function sendPutRequest(string $endpoint, array $putData = []): string
    {
        return $this->sendAuthenticatedHttpRequest('put', $endpoint, ['form_params' => $putData]);
    }

    private function sendAuthenticatedHttpRequest(string $method, string $endpoint, array $options = []): string
    {
        $options['auth'] = [$this->authentication->getUserName(), $this->authentication->getPassword()];
        return $this->sendSimpleHttpRequest($method, $endpoint, $options);
    }

    private function sendSimpleHttpRequest(string $method, string $endpoint, array $options = []): string
    {
        return $this->httpClient->{$method}($method, $endpoint, $options);
    }

    /**
     * @param \DOMDocument $domDocument
     * @throws EndpointException
     */
    private function handleAnyErrors(\DOMDocument $domDocument): void
    {
        $errors = $domDocument->getElementsByTagName('errors');

        if ($errors->length > 0) {
            throw new EndpointException(...$this->buildEndpointErrors($errors));
        }
    }

    private function getTagContentByName(string $name, \DOMDocument $response): string
    {
        return $response->getElementsByTagName($name)->item(0)->textContent;
    }

    private function toDomDocument(string $xml): \DOMDocument
    {
        $domDocument = new \DOMDocument();
        $domDocument->loadXML($xml);
        return $domDocument;
    }

    /**
     * @param \DOMDocument $response
     * @return MessageDeliveryReport
     * @throws EndpointException
     */
    private function handleSendMessageResponse(\DOMDocument $response): MessageDeliveryReport
    {

        $this->handleAnyErrors($response);

        $messageId = $this->getTagContentByName('message_id', $response);
        $scheduleId = $this->getTagContentByName('scheduled_id', $response);
        $creditsUsed = (int) $this->getTagContentByName('credits_used', $response);
        $status = $this->getTagContentByName('status', $response);

        switch ($status) {
            case 'SENT':
                return MessageDeliveryReport::createSent($messageId, $creditsUsed);
                break;
            case 'QUEUED':
                return MessageDeliveryReport::createQueued($messageId, $creditsUsed);
                break;
            case 'SCHEDULED':
                return MessageDeliveryReport::createScheduled($scheduleId, $creditsUsed);
                break;
        }

        throw new EndpointException(new EndpointError(
            -1,
            'Unexpected status from the endpoint: ' . $status
        ));
    }

    /**
     * @param $response
     * @throws EndpointException
     */
    private function handleDeleteScheduleMessageResponse($response): void
    {
        $this->handleAnyErrors($response);
    }

    /**
     * @param string|null $accountId
     * @return AccountInformation
     * @throws EndpointException
     */
    private function fetchAccountInformation(string $accountId = null): AccountInformation
    {
        $response = $this->toDomDocument(
            $this->sendGetRequest($this->buildEndpointUri(...array_filter(['account', $accountId])))
        );

        return $this->handleAccountResponse($response);
    }

    /**
     * @param \DOMDocument $response
     * @return AccountInformation
     * @throws EndpointException
     */
    private function handleAccountResponse(\DOMDocument $response): AccountInformation
    {
        $this->handleAnyErrors($response);

        return new AccountInformation(
            $this->getTagContentByName('account_id', $response),
            $this->getTagContentByName('api_username', $response),
            $this->getTagContentByName('api_password', $response),
            $this->getTagContentByName('company_name', $response),
            new \DateTimeImmutable($this->getTagContentByName('create_date', $response)),
            (int) $this->getTagContentByName('credits', $response),
            $this->getTagContentByName('notification_email', $response),
            $this->getTagContentByName('notification_mobile', $response),
            $this->getTagContentByName('username', $response),
            $this->getTagContentByName('password', $response)
        );
    }

    /**
     * @param \DOMDocument $response
     * @return SendGroup
     * @throws EndpointException
     */
    private function handleGroupResponse(\DOMDocument $response): SendGroup
    {
        $this->handleAnyErrors($response);

        $groupElement = $response->getElementsByTagName('group')->item(0);

        $numberElements = $groupElement->getElementsByTagName('number');
        $numbers = [];

        /** @var \DOMNode $numberElement */
        foreach ($numberElements as $numberElement) {
            $numbers[] = $numberElement->textContent;
        }

        try {
            return new SendGroup(
                $groupElement->attributes->getNamedItem('id')->textContent,
                $groupElement->attributes->getNamedItem('name')->textContent,
                $groupElement->attributes->getNamedItem('is_stop')->textContent !== 'false',
                new PhoneNumberCollection($numbers)
            );
        } catch (InvalidMessageException $e) {
            throw new EndpointException(new EndpointError(-1, 'Could not parse returned numbers: '  . $e->getMessage()));
        }
    }
}