<?php declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Shipping\Test\Unit\Controller\Adminhtml\Order\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Helper\Data;
use Magento\Backend\Model\Menu;
use Magento\Backend\Model\Session;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Message\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Order\Shipment;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\NewAction;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;
use Magento\Shipping\Model\ShipmentProviderInterface;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class NewActionTest extends TestCase
{
    /**
     * @var ShipmentLoader|MockObject
     */
    protected $shipmentLoader;

    /**
     * @var NewAction
     */
    protected $newAction;

    /**
     * @var Action\Context|MockObject
     */
    protected $context;

    /**
     * @var Http|MockObject
     */
    protected $request;

    /**
     * @var ResponseInterface|MockObject
     */
    protected $response;

    /**
     * @var Manager|MockObject
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\ObjectManager\ObjectManager|MockObject
     */
    protected $objectManager;

    /**
     * @var Session|MockObject
     */
    protected $session;

    /**
     * @var ActionFlag|MockObject
     */
    protected $actionFlag;

    /**
     * @var Data|MockObject
     */
    protected $helper;

    /**
     * @var  ViewInterface|MockObject
     */
    protected $view;

    /**
     * @var Page|MockObject
     */
    protected $resultPageMock;

    /**
     * @var Config|MockObject
     */
    protected $pageConfigMock;

    /**
     * @var Title|MockObject
     */
    protected $pageTitleMock;

    /**
     * @var ShipmentProviderInterface|MockObject
     */
    private $shipmentProviderMock;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function setUp(): void
    {
        $objectManagerHelper = new ObjectManagerHelper($this);
        $this->shipmentLoader = $this->getMockBuilder(
            ShipmentLoader::class
        )->disableOriginalConstructor()
            ->setMethods(['setShipmentId', 'setOrderId', 'setShipment', 'setTracking', 'load'])
            ->getMock();
        $labelGenerator = $this->getMockBuilder(LabelGenerator::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $shipmentSender = $this->getMockBuilder(ShipmentSender::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $this->context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->context = $this->createPartialMock(Context::class, [
                'getRequest', 'getResponse', 'getMessageManager', 'getRedirect', 'getObjectManager',
                'getSession', 'getActionFlag', 'getHelper', 'getView'
            ]);
        $this->response = $this->createPartialMock(
            ResponseInterface::class,
            ['setRedirect', 'sendResponse']
        );
        $this->request = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()->getMock();
        $this->messageManager = $this->createPartialMock(
            Manager::class,
            ['addSuccess', 'addError']
        );
        $this->session = $this->createPartialMock(
            Session::class,
            ['setIsUrlNotice', 'getCommentText']
        );
        $this->shipmentProviderMock = $this->getMockBuilder(ShipmentProviderInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getShipmentData'])
            ->getMockForAbstractClass();
        $this->actionFlag = $this->createPartialMock(ActionFlag::class, ['get']);
        $this->helper = $this->createPartialMock(Data::class, ['getUrl']);
        $this->view = $this->createMock(ViewInterface::class);
        $this->resultPageMock = $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pageConfigMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pageTitleMock = $this->getMockBuilder(Title::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->context->expects($this->once())
            ->method('getMessageManager')
            ->will($this->returnValue($this->messageManager));
        $this->context->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue($this->request));
        $this->context->expects($this->once())
            ->method('getResponse')
            ->will($this->returnValue($this->response));
        $this->context->expects($this->once())
            ->method('getObjectManager')
            ->will($this->returnValue($this->objectManager));
        $this->context->expects($this->once())
            ->method('getSession')
            ->will($this->returnValue($this->session));
        $this->context->expects($this->once())
            ->method('getActionFlag')
            ->will($this->returnValue($this->actionFlag));
        $this->context->expects($this->once())
            ->method('getHelper')
            ->will($this->returnValue($this->helper));
        $this->context->expects($this->once())->method('getView')->will($this->returnValue($this->view));
        $this->newAction = $objectManagerHelper->getObject(
            NewAction::class,
            [
                'context' => $this->context, 'shipmentLoader' => $this->shipmentLoader, 'request' => $this->request,
                'response' => $this->response, 'view' => $this->view, 'shipmentProvider' => $this->shipmentProviderMock
            ]
        );
    }

    public function testExecute()
    {
        $shipmentId = 1000012;
        $orderId = 10003;
        $tracking = [];
        $shipmentData = ['items' => [], 'send_email' => ''];
        $shipment = $this->createPartialMock(
            Shipment::class,
            ['load', 'save', 'register', 'getOrder', 'getOrderId', '__wakeup']
        );
        $this->request->expects($this->any())
            ->method('getParam')
            ->will(
                $this->returnValueMap(
                    [
                        ['order_id', null, $orderId],
                        ['shipment_id', null, $shipmentId],
                        ['tracking', null, $tracking],
                    ]
                )
            );
        $this->shipmentLoader->expects($this->any())
            ->method('setShipmentId')
            ->with($shipmentId);
        $this->shipmentLoader->expects($this->any())
            ->method('setOrderId')
            ->with($orderId);
        $this->shipmentLoader->expects($this->any())
            ->method('setShipment')
            ->with($shipmentData);
        $this->shipmentLoader->expects($this->any())
            ->method('setTracking')
            ->with($tracking);
        $this->shipmentLoader->expects($this->once())
            ->method('load')
            ->will($this->returnValue($shipment));
        $this->session->expects($this->once())
            ->method('getCommentText')
            ->with(true)
            ->will($this->returnValue(''));
        $this->objectManager->expects($this->atLeastOnce())
            ->method('get')
            ->with(Session::class)
            ->will($this->returnValue($this->session));
        $this->view->expects($this->once())
            ->method('loadLayout')
            ->will($this->returnSelf());
        $this->view->expects($this->once())
            ->method('renderLayout')
            ->will($this->returnSelf());
        $this->view->expects($this->any())
            ->method('getPage')
            ->willReturn($this->resultPageMock);
        $this->resultPageMock->expects($this->any())
            ->method('getConfig')
            ->willReturn($this->pageConfigMock);
        $this->pageConfigMock->expects($this->any())
            ->method('getTitle')
            ->willReturn($this->pageTitleMock);
        $layout = $this->createMock(LayoutInterface::class);
        $menuBlock = $this->createPartialMock(
            BlockInterface::class,
            ['toHtml', 'setActive', 'getMenuModel']
        );
        $menuModel = $this->getMockBuilder(Menu::class)
            ->disableOriginalConstructor()->getMock();
        $itemId = 'Magento_Sales::sales_order';
        $parents = [
            new DataObject(['title' => 'title1']),
            new DataObject(['title' => 'title2']),
            new DataObject(['title' => 'title3']),
        ];
        $menuModel->expects($this->once())
            ->method('getParentItems')
            ->with($itemId)
            ->will($this->returnValue($parents));
        $menuBlock->expects($this->once())
            ->method('setActive')
            ->with($itemId);
        $menuBlock->expects($this->once())
            ->method('getMenuModel')
            ->will($this->returnValue($menuModel));
        $this->view->expects($this->once())
            ->method('getLayout')
            ->will($this->returnValue($layout));
        $layout->expects($this->once())
            ->method('getBlock')
            ->with('menu')
            ->will($this->returnValue($menuBlock));
        $this->shipmentProviderMock->expects($this->once())
            ->method('getShipmentData')
            ->willReturn($shipmentData);

        $this->assertNull($this->newAction->execute());
    }
}
