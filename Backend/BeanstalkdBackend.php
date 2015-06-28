<?php
namespace Sonata\NotificationBundle\Backend;


use Sonata\NotificationBundle\Model\MessageInterface;
use Sonata\NotificationBundle\Consumer\ConsumerEvent;
use Sonata\NotificationBundle\Model\Message;
use Sonata\NotificationBundle\Exception\HandlingException;
use Sonata\NotificationBundle\Iterator\AMQPMessageIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Sonata\NotificationBundle\Iterator\BeanstalkdIterator;
use ZendDiagnostics\Result\Failure;
use ZendDiagnostics\Result\Success;

/**
*  
* 
*/
class BeanstalkdBackend implements BackendInterface
{
    protected $dispatcher = null;
    protected $tubeName;
    
    public function __construct($tube)
    {
        $this->tubeName = $tube;
    }

    public function setTube($tube) {

        $this->tubeName = $tube;
        return $this;
    }

    public function setDispatcher(BeanstalkdBackendDispatcher $dp)
    {
        $this->dispatcher = $dp;
    }

    public function initialize() {

    }

     /**
     * {@inheritdoc}
     */
    public function publish(MessageInterface $message)
    {
        $body = json_encode(array(
            'type'      => $message->getType(),
            'body'      => $message->getBody(),
            'createdAt' => $message->getCreatedAt()->format('U'),
            'state'     => $message->getState()
        ));

        $this->getTube()->put($body);
    }
    /**
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    protected function getTube($tubeName = null)
    {
        if ($tubeName == null) {
            $tubeName = $this->tubeName;
        }
        if ($this->dispatcher === null) {
            throw new \RuntimeException('Unable to retrieve tube without dispatcher.');
        }
        $tube = $this->dispatcher->getConnection();
        $tube->useTube($tubeName);
        return $tube;
    }

       /**
     * {@inheritdoc}
     */
    public function create($type, array $body)
    {
        $message = new Message();
        $message->setType($type);
        $message->setBody($body);
        $message->setState(MessageInterface::STATE_OPEN);
        return $message;
    }
    /**
     * {@inheritdoc}
     */
    public function createAndPublish($type, array $body)
    {
        return $this->publish($this->create($type, $body));
    }
    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new BeanstalkdIterator($this->dispatcher->getConnection(), $this->tubeName);
    }
    /**
     * {@inheritdoc}
     */
    public function handle(MessageInterface $message, EventDispatcherInterface $dispatcher) 
    {
        $event = new ConsumerEvent($message);
        try {
            $message->setStartedAt(new \DateTime());
            $message->setState(MessageInterface::STATE_IN_PROGRESS);
            $dispatcher->dispatch($message->getType(), $event);
            $message->setCompletedAt(new \DateTime());
            $message->setState(MessageInterface::STATE_DONE);
            $this->getTube()->delete($message->getValue('Job'));
            return $event->getReturnInfo();
        } catch (\Exception $e) {
            $message->setCompletedAt(new \DateTime());
            $message->setState(MessageInterface::STATE_ERROR);
            $this->getTube()->release($message->getValue('Job'));
            throw new HandlingException('Error while handling a message', 0, $e);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return new Success('Channel is running (RabbitMQ)');
    }
    /**
     * {@inheritdoc}
     */
    public function cleanup()
    {
        throw new \RuntimeException('Not implemented');
    }
}