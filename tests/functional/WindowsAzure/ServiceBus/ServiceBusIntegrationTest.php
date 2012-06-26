<?php

/**
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * PHP version 5
 *
 * @category  Microsoft
 * @package   Tests\Functional\WindowsAzure\ServiceBus
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link      https://github.com/windowsazure/azure-sdk-for-php
 */

namespace Tests\Functional\WindowsAzure\ServiceBus;

use Tests\Functional\WindowsAzure\ServiceBus\IntegrationTestBase;
use WindowsAzure\Common\Internal\IServiceFilter;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\QueueDescription;
use WindowsAzure\ServiceBus\Models\QueueInfo;
use WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;
use WindowsAzure\ServiceBus\Models\RuleInfo;
use WindowsAzure\ServiceBus\Models\SubscriptionInfo;
use WindowsAzure\ServiceBus\Models\TopicInfo;

class ServiceBusIntegrationTest extends IntegrationTestBase
{
    private $RECEIVE_AND_DELETE_5_SECONDS;
    private $PEEK_LOCK_5_SECONDS;

    public function setUp()
    {
        parent::setUp();
        $this->RECEIVE_AND_DELETE_5_SECONDS = new ReceiveMessageOptions();
        $this->RECEIVE_AND_DELETE_5_SECONDS->setReceiveAndDelete();
        $this->RECEIVE_AND_DELETE_5_SECONDS->setTimeout(5);

        $this->PEEK_LOCK_5_SECONDS = new ReceiveMessageOptions();
        $this->PEEK_LOCK_5_SECONDS->setPeekLock();
        $this->PEEK_LOCK_5_SECONDS->setTimeout(5);
    }

//    public function testCreateService() {
//        // reinitialize configuration from known state
//        $config = createConfiguration();
//
//        // applied as default configuration
//        Configuration->setInstance($config);
//        $service = ServiceBusService->create();
//    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::getQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::listQueues
    */
    public function testFetchQueueAndListQueuesWorks()
    {
        // Arrange

        // Act
        $entry = $this->restProxy->getQueue('TestAlpha');
        $feed = $this->restProxy->listQueues();

        // Assert
        $this->assertNotNull($entry, '$entry');
        $this->assertNotNull($feed, '$feed');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    */
    public function testCreateQueueWorks()
    {
        // Act
        $queue = null;
        $queue = new QueueInfo('TestCreateQueueWorks');

        $queueDescription = new QueueDescription();
        $queueDescription->setMaxSizeInMegabytes(1024);

        $queue->setQueueDescription($queueDescription);
        $saved = $this->restProxy->createQueue($queue);

        // Assert
        $this->assertNotNull($saved, '$saved');
        $this->assertNotSame($queue, $saved, 'queue and saved');
        $this->assertEquals('TestCreateQueueWorks', $saved->getTitle(), '$saved->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::deleteQueue
    */
    public function testDeleteQueueWorks()
    {
        // Arrange
        try {
            $this->restProxy->createQueue(new QueueInfo('TestDeleteQueueWorks'));
        } catch (ServiceException $e) {
            // Ignore
        }

        // Act
        $result = $this->restProxy->deleteQueue('TestDeleteQueueWorks');

        // Assert
        $this->assertNull($result, '$result');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    */
    public function testSendMessageWorks()
    {
        // Arrange
        $message = new BrokeredMessage('sendMessageWorks');

        // Act
        $this->restProxy->sendQueueMessage('TestAlpha', $message);

        // Assert
        $this->assertTrue(true, 'no error');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    */
    public function testReceiveMessageWorks()
    {
        // Arrange
        $queueName = 'TestReceiveMessageWorks';
        $this->restProxy->createQueue(new QueueInfo($queueName));
        $this->restProxy->sendQueueMessage($queueName, new BrokeredMessage('Hello World'));

        // Act
        $message = $this->restProxy->receiveQueueMessage($queueName, $this->RECEIVE_AND_DELETE_5_SECONDS);
        $data = $message->getBody();
        $size = strlen($data);

        // Assert
        $this->assertEquals(11, $size, '$size');
        $this->assertEquals($data, 'Hello World');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    */
    public function testPeekLockMessageWorks()
    {
        // Arrange
        $queueName = 'TestPeekLockMessageWorks';
        $this->restProxy->createQueue(new QueueInfo($queueName));
        $this->restProxy->sendQueueMessage($queueName, new BrokeredMessage('Hello Again'));

        // Act
        $message = $this->restProxy->receiveQueueMessage($queueName, $this->PEEK_LOCK_5_SECONDS);

        // Assert
        $data = $message->getBody();
        $size = strlen($data);
        $this->assertEquals(11, $size, '$size');
        $this->assertEquals('Hello Again', $data, 'new String($data, 0, $size)');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::deleteMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    */
    public function testPeekLockedMessageCanBeCompleted()
    {
        // Arrange
        $queueName = 'TestPeekLockedMessageCanBeCompleted';
        $this->restProxy->createQueue(new QueueInfo($queueName));
        $this->restProxy->sendQueueMessage($queueName, new BrokeredMessage('Hello Again'));
        $message = $this->restProxy->receiveQueueMessage($queueName, $this->PEEK_LOCK_5_SECONDS);

        // Act
        $lockToken = $message->getLockToken();
        $lockedUntil = $message->getLockedUntilUtc();
        $lockLocation = $message->getLockLocation();

        $this->restProxy->deleteMessage($message);

        // Assert
        $this->assertNotNull($lockToken, '$lockToken');
        $this->assertNotNull($lockedUntil, '$lockedUntil');
        $this->assertNotNull($lockLocation, '$lockLocation');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::unlockMessage
    */
    public function testPeekLockedMessageCanBeUnlocked()
    {
        // Arrange
        $queueName = 'TestPeekLockedMessageCanBeUnlocked';
        $this->restProxy->createQueue(new QueueInfo($queueName));
        $this->restProxy->sendQueueMessage($queueName, new BrokeredMessage('Hello Again'));
        $peekedMessage = $this->restProxy->receiveQueueMessage($queueName, $this->PEEK_LOCK_5_SECONDS);

        // Act
        $lockToken = $peekedMessage->getLockToken();
        $lockedUntil = $peekedMessage->getLockedUntilUtc();

        $this->restProxy->unlockMessage($peekedMessage);
        $receivedMessage = $this->restProxy->receiveQueueMessage($queueName, $this->RECEIVE_AND_DELETE_5_SECONDS);

        // Assert
        $this->assertNotNull($lockToken, '$lockToken');
        $this->assertNotNull($lockedUntil, '$lockedUntil');
        $this->assertNull($receivedMessage->getLockToken(), '$receivedMessage->getLockToken()');
        $this->assertNull($receivedMessage->getLockedUntilUtc(), '$receivedMessage->getLockedUntilUtc()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::deleteMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    */
    public function testPeekLockedMessageCanBeDeleted()
    {
        // Arrange
        $queueName = 'TestPeekLockedMessageCanBeDeleted';
        $this->restProxy->createQueue(new QueueInfo($queueName));
        $this->restProxy->sendQueueMessage($queueName, new BrokeredMessage('Hello Again'));
        $peekedMessage = $this->restProxy->receiveQueueMessage($queueName, $this->PEEK_LOCK_5_SECONDS);

        // Act
        $lockToken = $peekedMessage->getLockToken();
        $lockedUntil = $peekedMessage->getLockedUntilUtc();

        $this->restProxy->deleteMessage($peekedMessage);
        $receivedMessage = $this->restProxy->receiveQueueMessage($queueName, $this->RECEIVE_AND_DELETE_5_SECONDS);

        // Assert
        $this->assertNotNull($lockToken, '$lockToken');
        $this->assertNotNull($lockedUntil, '$lockedUntil');
        $this->assertNull($receivedMessage, '$receivedMessage');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    */
    public function testContentTypePassesThrough()
    {
        // Arrange
        $queueName = 'TestContentTypePassesThrough';
        $this->restProxy->createQueue(new QueueInfo($queueName));

        // Act
        $message = new BrokeredMessage('<data>Hello Again</data>');
        $message->setContentType('text/xml');
        $this->restProxy->sendQueueMessage($queueName, $message);

        $message = $this->restProxy->receiveQueueMessage($queueName, $this->RECEIVE_AND_DELETE_5_SECONDS);

        // Assert
        $this->assertNotNull($message, '$message');
        $this->assertEquals('text/xml', $message->getContentType(), '$message->getContentType()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::deleteTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::getTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::listTopics
    */
    public function testTopicCanBeCreatedListedFetchedAndDeleted()
    {
        // Arrange
        $topicName = 'TestTopicCanBeCreatedListedFetchedAndDeleted';

        // Act
        $topic = new TopicInfo($topicName);
        $created = $this->restProxy->createTopic($topic);

        $listed = $this->restProxy->listTopics();
        $fetched = $this->restProxy->getTopic($topicName);
        $this->restProxy->deleteTopic($topicName);
        $listed2 = $this->restProxy->listTopics();

        // Assert
        $this->assertNotNull($created, '$created');
        $this->assertNotNull($listed, '$listed');
        $this->assertNotNull($fetched, '$fetched');
        $this->assertNotNull($listed2, '$listed2');

        $this->assertEquals(count($listed->getTopicInfos()) - 1, count($listed2->getTopicInfos()), '$listed2->getItems()->size()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::withFilter
    */
    public function testFilterCanSeeAndChangeRequestOrResponse()
    {
        // Arrange
        $customServiceFilter = new CustomServiceFilter();
        $filtered = $this->restProxy->withFilter($customServiceFilter);

        // Act
        $queueInfo = new QueueInfo('TestFilterCanSeeAndChangeRequestOrResponse');
        $created = $filtered->createQueue($queueInfo);

        // Assert
        $this->assertNotNull($created, '$created');
        $this->assertEquals(1, count($customServiceFilter->requests), 'requests->size()');
        $this->assertEquals(1, count($customServiceFilter->responses), 'responses->size()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    */
    public function testSubscriptionsCanBeCreatedOnTopics()
    {
        // Arrange
        $topicName = 'TestSubscriptionsCanBeCreatedOnTopics';
        $this->restProxy->createTopic(new TopicInfo($topicName));

        // Act
        $created = $this->restProxy->createSubscription($topicName, new SubscriptionInfo('MySubscription'));

        // Assert
        $this->assertNotNull($created, '$created');
        $this->assertEquals('MySubscription', $created->getTitle(), '$created->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::listSubscriptions
    */
    public function testSubscriptionsCanBeListed()
    {
        // Arrange
        $topicName = 'TestSubscriptionsCanBeListed';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('MySubscription2'));

        // Act
        $result = $this->restProxy->listSubscriptions($topicName);

        // Assert
        $this->assertNotNull($result, '$result');
        $items = $result->getSubscriptionInfos();
        $this->assertEquals(1, count($items), '$result->getItems()->size()');
        $this->assertEquals('MySubscription2', $items[0]->getTitle(), '$result->getItems()->get(0)->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::getSubscription
    */
    public function testSubscriptionsDetailsMayBeFetched()
    {
        // Arrange
        $topicName = 'TestSubscriptionsDetailsMayBeFetched';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('MySubscription3'));

        // Act
        $result = $this->restProxy->getSubscription($topicName, 'MySubscription3');

        // Assert
        $this->assertNotNull($result, '$result');
        $this->assertEquals('MySubscription3', $result->getTitle(), '$result->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::deleteSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::listSubscriptions
    */
    public function testSubscriptionsMayBeDeleted()
    {
        // Arrange
        $topicName = 'TestSubscriptionsMayBeDeleted';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('MySubscription4'));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('MySubscription5'));

        // Act
        $this->restProxy->deleteSubscription($topicName, 'MySubscription4');

        // Assert
        $result = $this->restProxy->listSubscriptions($topicName);
        $this->assertNotNull($result, '$result');
        $this->assertEquals(1, count($result->getSubscriptionInfos()), '$result->getItems()->size()');
        $items = $result->getSubscriptionInfos();
        $this->assertEquals('MySubscription5', $items[0]->getTitle(), '$result->getItems()->get(0)->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveSubscriptionMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendTopicMessage
    */
    public function testSubscriptionWillReceiveMessage()
    {
        // Arrange
        $topicName = 'TestSubscriptionWillReceiveMessage';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('sub'));
        // Act
        $message = new BrokeredMessage('<p>Testing subscription</p>');
        $message->setContentType('text/html');
        $this->restProxy->sendTopicMessage($topicName, $message);

        // Act
        $message = $this->restProxy->receiveSubscriptionMessage($topicName, 'sub', $this->RECEIVE_AND_DELETE_5_SECONDS);

        // Assert
        $this->assertNotNull($message, '$message');

        $this->assertEquals('<p>Testing subscription</p>', $message->getBody(), '$message->getBody())');
        $this->assertEquals('text/html', $message->getContentType(), '$message->getContentType()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createRule
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    */
    public function testRulesCanBeCreatedOnSubscriptions()
    {
        // Arrange
        $topicName = 'TestrulesCanBeCreatedOnSubscriptions';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('sub'));

        // Act
        $created = $this->restProxy->createRule($topicName, 'sub', new RuleInfo('MyRule1'));

        // Assert
        $this->assertNotNull($created, '$created');
        $this->assertEquals('MyRule1', $created->getTitle(), '$created->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createRule
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::listRules
    */
    public function testRulesCanBeListedAndDefaultRuleIsPrecreated()
    {
        // Arrange
        $topicName = 'TestrulesCanBeListedAndDefaultRuleIsPrecreated';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('sub'));
        $this->restProxy->createRule($topicName, 'sub', new RuleInfo('MyRule2'));

        // Act
        $result = $this->restProxy->listRules($topicName, 'sub');

        // Assert
        $this->assertNotNull($result, '$result');
        $this->assertEquals(2, count($result->getRuleInfos()), '$result->getItems()->size()');
        $items = $result->getRuleInfos();
        $rule0 = $items[0];
        $rule1 = $items[1];
        if ($rule0->getTitle() == 'MyRule2') {
            $swap = $rule1;
            $rule1 = $rule0;
            $rule0 = $swap;
        }

        $this->assertEquals('$Default', $rule0->getTitle(), '$rule0->getTitle()');
        $this->assertEquals('MyRule2', $rule1->getTitle(), '$rule1->getTitle()');
        $items = $result->getRuleInfos();
        $this->assertNotNull($items[0], '$result->getItems()->get(0)');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::getRule
    */
    public function testRuleDetailsMayBeFetched()
    {
        // Arrange
        $topicName = 'TestruleDetailsMayBeFetched';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('sub'));

        // Act
        $result = $this->restProxy->getRule($topicName, 'sub', '$Default');

        // Assert
        $this->assertNotNull($result, '$result');
        $this->assertEquals('$Default', $result->getTitle(), '$result->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createRule
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::deleteRule
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::listRules
    */
    public function testRulesMayBeDeleted()
    {
        // Arrange
        $topicName = 'TestRulesMayBeDeleted';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('sub'));
        $this->restProxy->createRule($topicName, 'sub', new RuleInfo('MyRule4'));
        $this->restProxy->createRule($topicName, 'sub', new RuleInfo('MyRule5'));

        // Act
        $this->restProxy->deleteRule($topicName, 'sub', 'MyRule5');
        $this->restProxy->deleteRule($topicName, 'sub', '$Default');

        // Assert
        $result = $this->restProxy->listRules($topicName, 'sub');
        $this->assertNotNull($result, '$result');

        $this->assertEquals(1, count($result->getRuleInfos()), '$result->getItems()->size()');
        $items = $result->getRuleInfos();
        $this->assertEquals('MyRule4', $items[0]->getTitle(), '$result->getItems()->get(0)->getTitle()');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createRule
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createSubscription
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createTopic
    */
    public function testRulesMayHaveActionAndFilter()
    {
        // Arrange
        $topicName = 'TestRulesMayHaveAnActionAndFilter';
        $this->restProxy->createTopic(new TopicInfo($topicName));
        $this->restProxy->createSubscription($topicName, new SubscriptionInfo('sub'));

        // Act
        $ruleInfoOne = new RuleInfo('One');
        $ruleInfoOne->withCorrelationFilter('my-id');
        $ruleOne = $this->restProxy->createRule($topicName, 'sub', $ruleInfoOne);
        $ruleInfoTwo = new RuleInfo('Two');
        $ruleInfoTwo->withTrueFilter();
        $ruleTwo = $this->restProxy->createRule($topicName, 'sub', $ruleInfoTwo);
        $ruleInfoThree = new RuleInfo('Three');
        $ruleInfoThree->withFalseFilter();
        $ruleThree = $this->restProxy->createRule($topicName, 'sub', $ruleInfoThree);
        $ruleInfoFour = new RuleInfo('Four');
        $ruleInfoFour->withEmptyRuleAction();
        $ruleFour = $this->restProxy->createRule($topicName, 'sub', $ruleInfoFour);
        $ruleInfoFive = new RuleInfo('Five');
        $ruleInfoFive->withSqlRuleAction('SET x = 5');
        $ruleFive = $this->restProxy->createRule($topicName, 'sub', $ruleInfoFive);
        $ruleInfoSix = new RuleInfo('Six');
        $ruleInfoSix->withSqlFilter('x != 5');
        $ruleSix = $this->restProxy->createRule($topicName, 'sub', $ruleInfoSix);

        // Assert
        $this->assertTrue(
                $ruleOne->getFilter()
                instanceof \WindowsAzure\ServiceBus\Models\CorrelationFilter,
                '$ruleOne->getFilter() instanceof CorrelationFilter');
        $this->assertTrue(
                $ruleTwo->getFilter()
                instanceof \WindowsAzure\ServiceBus\Models\TrueFilter,
                '$ruleTwo->getFilter() instanceof TrueFilter');
        $this->assertTrue(
                $ruleThree->getFilter()
                instanceof \WindowsAzure\ServiceBus\Models\FalseFilter,
                '$ruleThree->getFilter() instanceof FalseFilter');
        $this->assertTrue(
                $ruleFour->getAction()
                instanceof \WindowsAzure\ServiceBus\Models\EmptyRuleAction,
                '$ruleFour->getAction() instanceof EmptyRuleAction');
        $this->assertTrue(
                $ruleFive->getAction()
                instanceof \WindowsAzure\ServiceBus\Models\SqlRuleAction,
                '$ruleFive->getAction() instanceof SqlRuleAction');
        $this->assertTrue(
                $ruleSix->getFilter()
                instanceof \WindowsAzure\ServiceBus\Models\SqlFilter,
                '$ruleSix->getFilter() instanceof SqlFilter');
    }

    /**
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::createQueue
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::receiveQueueMessage
    * @covers WindowsAzure\ServiceBus\ServiceBusRestProxy::sendQueueMessage
    */
    public function testMessagesMayHaveCustomProperties()
    {
        // Arrange
        $queueName = 'TestMessagesMayHaveCustomProperties';
        $this->restProxy->createQueue(new QueueInfo($queueName));

        // Act
        $message = new BrokeredMessage('');
        $message->setProperty('hello', 'world');
        $message->setProperty('foo', 42);
        $this->restProxy->sendQueueMessage($queueName, $message);
        $message = $this->restProxy->receiveQueueMessage($queueName, $this->RECEIVE_AND_DELETE_5_SECONDS);

        // Assert
        $this->assertEquals('world', $message->getProperty('hello'), '$message->getProperty(\'hello\')');
        $this->assertEquals(42, $message->getProperty('foo'), '$message->getProperty(\'foo\')');
    }
}

class CustomServiceFilter implements IServiceFilter
{
    public $requests = array();
    public $responses = array();

    public function handleRequest($request)
    {
        return $request;
    }

    public function handleResponse($request, $response)
    {
        array_push($this->requests, $request);
        array_push($this->responses, $response);
        return $response;
    }
}

?>