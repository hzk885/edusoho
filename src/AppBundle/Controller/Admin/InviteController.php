<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Common\Paginator;
use AppBundle\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;

class InviteController extends BaseController
{
    public function recordAction(Request $request)
    {
        $conditions = $request->query->all();
        $conditions = ArrayToolkit::parts($conditions, array('nickname', 'startDate', 'endDate'));

        $page = $request->query->get('page', 0);

        if (!empty($conditions['nickname']) && empty($page)) {
            $user = $this->getUserService()->getUserByNickname($conditions['nickname']);
            $conditions['inviteUserId'] = empty($user) ? '0' : $user['id'];
            unset($conditions['nickname']);
            $invitedRecord = $this->getInvitedRecordByUserIdAndConditions($user, $conditions);
        }

        $recordCount = $this->getInviteRecordService()->countRecords($conditions);
        $paginator = new Paginator(
            $this->get('request'),
            $recordCount,
            20
        );

        $inviteRecords = $this->getInviteRecordService()->searchRecords(
            $conditions,
            array('inviteTime' => 'desc'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        if (!empty($invitedRecord)) {
            $inviteRecords = array_merge($invitedRecord, $inviteRecords);
        }
        // foreach ($inviteRecords as $record) {
        //     $orderInfo = $this->getInviteRecordService()->getOrderInfoByUserIdAndInviteTime($record['invitedUserId'], $record['inviteTime']);
        //     $fileds['amount'] = $orderInfo['totalPrice'];
        //     $this->getInviteRecordService()->updateOrderInfoById($record['id'], $orderInfo);
        // }
        $users = $this->getInviteRecordService()->getAllUsersByRecords($inviteRecords);

        return $this->render('admin/invite/records.html.twig', array(
            'records' => $inviteRecords,
            'users' => $users,
            'paginator' => $paginator,
        ));
    }

    public function flushOrderInfoAction()
    {
        $this->getInviteRecordService()->flushOrderInfo();

        return $this->createJsonpResponse('ok');
    }

    protected function getInvitedRecordByUserIdAndConditions($user, $conditions)
    {
        if (empty($user)) {
            return array();
        }
        $invitedRecordConditions = ArrayToolkit::parts($conditions, array('startDate', 'endDate'));
        $invitedRecordConditions['invitedUserId'] = $user['id'];
        $invitedRecord = $this->getInviteRecordService()->searchRecords(
            $invitedRecordConditions,
            array(),
            0,
            1
        );

        return ArrayToolkit::index($invitedRecord, 'id');
    }

    public function userRecordsAction(Request $request)
    {
        $conditions = $request->query->all();
        $conditions = ArrayToolkit::parts($conditions, array('nickname'));

        $paginator = new Paginator(
            $this->get('request'),
            $this->getUserService()->countUsers($conditions),
            20
        );

        $users = $this->getUserService()->searchUsers(
            $conditions,
            array('id' => 'ASC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $inviteInformations = $this->getInviteRecordService()->getInviteInformationsByUsers($users);

        return $this->render('admin/invite/user-record.html.twig', array(
            'paginator' => $paginator,
            'inviteInformations' => $inviteInformations,
        ));
    }

    public function inviteDetailAction(Request $request)
    {
        $inviteUserId = $request->query->get('inviteUserId');

        $details = array();

        $invitedRecords = $this->getInviteRecordService()->findRecordsByInviteUserId($inviteUserId);

        foreach ($invitedRecords as $key => $invitedRecord) {
            list($coinAmountTotalPrice, $amountTotalPrice, $totalPrice) = $this->getInviteRecordService()->getUserOrderDataByUserIdAndTime(
                 $invitedRecord['invitedUserId'],
                 $invitedRecord['inviteTime']
            );

            $user = $this->getUserService()->getUser($invitedRecord['invitedUserId']);

            if (!empty($user)) {
                $details[] = array(
                    'userId' => $user['id'],
                    'nickname' => $user['nickname'],
                    'totalPrice' => $totalPrice,
                    'amountTotalPrice' => $amountTotalPrice,
                    'coinAmountTotalPrice' => $coinAmountTotalPrice,
                );
            }
        }

        return $this->render('admin/invite/invite-modal.html.twig', array(
            'details' => $details,
        ));
    }

    public function couponAction(Request $request, $filter)
    {
        $fileds = $request->query->all();
        $conditions = array();
        $conditions = $this->_prepareQueryCondition($fileds);

        if ($filter == 'invite') {
            $conditions['inviteUserCardIdNotEqual'] = 0;
        } elseif ($filter == 'invited') {
            $conditions['invitedUserCardIdNotEqual'] = 0;
        }

        list($paginator, $cardInformations) = $this->getCardInformations($request, $conditions);

        if ($filter == 'invite') {
            $cardIds = ArrayToolkit::column($cardInformations, 'inviteUserCardId');
        } elseif ($filter == 'invited') {
            $cardIds = ArrayToolkit::column($cardInformations, 'invitedUserCardId');
        }

        $cards = $this->getCardService()->findCardsByCardIds($cardIds);
        list($coupons, $orders, $users) = $this->getCardsData($cards);

        return $this->render('admin/invite/coupon.html.twig', array(
            'paginator' => $paginator,
            'cardInformations' => $cardInformations,
            'filter' => $filter,
            'users' => $users,
            'coupons' => $coupons,
            'cards' => $cards,
            'orders' => $orders,
        ));
    }

    public function queryInviteCouponAction(Request $request)
    {
        $fileds = $request->query->all();
        $conditions = array();
        $conditions = $this->_prepareQueryCondition($fileds);
        $conditions['cardType'] = 'coupon';
        $cards = $this->getCardService()->searchCards(
            $conditions,
            array('id' => 'ASC'),
            0,
            PHP_INT_MAX
        );
        $cards = ArrayToolkit::index($cards, 'cardId');
        list($coupons, $orders, $users) = $this->getCardsData($cards);
        $conditions = array();
        $conditions['inviteUserCardIds'] = empty($cards) ? array(-1) : ArrayToolkit::column($cards, 'cardId');
        list($paginator, $cardInformations) = $this->getCardInformations($request, $conditions);

        return $this->render('admin/invite/coupon.html.twig', array(
            'paginator' => $paginator,
            'cardInformations' => $cardInformations,
            'filter' => 'invite',
            'users' => $users,
            'coupons' => $coupons,
            'cards' => $cards,
            'orders' => $orders,
        ));
    }

    private function _prepareQueryCondition($fileds)
    {
        $conditions = array();

        if (!empty($fileds['nickname'])) {
            $conditions['nickname'] = $fileds['nickname'];
        }

        if (!empty($fileds['startDateTime'])) {
            $conditions['startDateTime'] = strtotime($fileds['startDateTime']);
        }

        if (!empty($fileds['endDateTime'])) {
            $conditions['endDateTime'] = strtotime($fileds['endDateTime']);
        }

        return $conditions;
    }

    private function getCardsData($cards)
    {
        $coupons = $this->getCouponService()->findCouponsByIds(ArrayToolkit::column($cards, 'cardId'));

        $orders = $this->getOrderService()->findOrdersByIds(ArrayToolkit::column($coupons, 'orderId'));

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($cards, 'userId'));

        return array($coupons, $orders, $users);
    }

    private function getCardInformations($request, $conditions)
    {
        $paginator = new Paginator(
            $request,
            $this->getInviteRecordService()->countRecords($conditions),
            20
        );

        $cardInformations = $this->getInviteRecordService()->searchRecords(
            $conditions,
            array('inviteTime' => 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        return array($paginator, $cardInformations);
    }

    protected function getInviteRecordService()
    {
        return $this->createService('User:InviteRecordService');
    }

    protected function getOrderService()
    {
        return $this->createService('Order:OrderService');
    }

    protected function getUserService()
    {
        return $this->createService('User:UserService');
    }

    protected function getCardService()
    {
        return $this->createService('Card:CardService');
    }

    protected function getCouponService()
    {
        return $this->createService('Coupon:CouponService');
    }
}
