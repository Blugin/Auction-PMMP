<?php
/**
 * @name Auction
 * @author alvin0319
 * @main alvin0319\Auction
 * @version 1.0.0
 * @api 4.0.0
 */
namespace alvin0319;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\item\Item;
use pocketmine\scheduler\Task;

class Auction extends PluginBase{

    /** @var array */
    public $item = [];

    /** @var array */
    public $seller = [];

    /** @var array */
    public $buyer = [];

    /** @var array */
    public $price = [];

    /** @var bool */
    public $is = false;

    public $task = [];
    /** @var string */
    public $prefix = '§e§l[ §f서버 §e] §r';

    public function onEnable(){
        $this->command = new PluginCommand('경매 시작', $this);
        $this->command->setDescription('경매를 시작합니다');
        $this->getServer()->getCommandMap()->register('경매 시작', $this->command);
        $this->cmd = new PluginCommand('경매 입찰', $this);
        $this->cmd->setDescription('경매를 입찰합니다');
        $this->getServer()->getCommandMap()->register('경매 입찰', $this->cmd);
        FA::$server = Server::getInstance();
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        if($command->getName() === '경매 시작'){
            if(!$sender instanceof Player){
                $sender->sendMessage($this->prefix . '콘솔에서는 사용하실수 없습니다');
                return true;
            }
            if(!isset($args[0]) or !is_numeric($args[0])){
                $sender->sendMessage($this->prefix . '최저가를 입력해주세요');
                return true;
            }
            if($this->is === true){
                $sender->sendMessage($this->prefix . '경매가 현재 진행중입니다');
                return true;
            }
            if($args[0] < 0){
                $sender->sendMessage($this->prefix . '장난하시는거죠?');
                return true;
            }
            $item = $sender->getInventory()->getItemInHand();
            if($item->getId() === 0){
                $sender->sendMessage($this->prefix . '설마 공기를 돈주고 파는건 아니겠죠??');
                return true;
            }
            $this->is = true;
            $this->buyer = $sender->getName();
            $this->seller = $sender->getName();
            $this->item = $item->jsonSerialize();
            $this->price = $args[0];
            $task = new AuctionTask($this);
            $this->getScheduler()->scheduleDelayedTask($task, 20 * 20);
            $this->task = $task->getTaskId();
            FA::broadcast($sender->getName() . ' 님이 ' . $item->getName() . '을(를) 최저가 ' . $args[0] . '원에 경매를 시작하였습니다!!');
        }
        if($command->getName() === '경매 입찰'){
            if($this->is !== true){
                $sender->sendMessage($this->prefix . '지금은 경매 진행중이 아닙니다');
                return true;
            }
            if(!$sender instanceof Player){
                $sender->sendMessage($this->prefix . '콘솔에서는 사용하실수 없습니다');
                return true;
            }
            if(!isset($args[0]) or !is_numeric($args[0])){
                $sender->sendMessage($this->prefix . '가격을 입력해주세요');
                return true;
            }
            if($args[0] < $this->price){
                $sender->sendMessage($this->prefix . '가격은 최저가보다 높아야 합니다');
                return true;
            }
            if($this->seller === $sender->getName()){
                $sender->sendMessage($this->prefix . '자신의 경매에는 참여할수 없습니다');
                return true;
            }
            $this->buyer = $sender->getName();
            $this->price = $args[0];
            $task = $this->task;
            $this->getScheduler()->cancelTask($task);
            $task = new AuctionTask($this);
            $this->getScheduler()->scheduleDelayedTask($task, 20 * 20);
            $this->task = $task->getTaskId();
            FA::broadcast($sender->getName() . ' 님이 ' . $args[0] . '원에 입찰하셨습니다');
        }
        return true;
    }
}
class AuctionTask extends Task{

    /** @var Auction */
    private $plugin;

    /** @var array */
    private $price;

    /** @var array */
    private $item;

    /** @var array */
    private $seller;

    /** @var array */
    private $buyer;

    /**
     * AuctionTask constructor.
     * @param Auction $plugin
     */
    public function __construct(Auction $plugin){
        $this->plugin = $plugin;
        $this->price = $this->plugin->price;
        $this->item = $this->plugin->item;
        $this->seller = $this->plugin->seller;
        $this->buyer = $this->plugin->buyer;
    }
    public function onRun(int $currentTick){
        $seller = FA::$server->getPlayer($this->seller);
        $buyer = FA::$server->getPlayer($this->buyer);
        if($this->buyer === $this->seller){
            FA::broadcast('아무도 입찰하지 않아 경매가 종료되었습니다');
            $this->price = [];
            $this->item = [];
            $this->seller = [];
            $this->buyer = [];
            $this->plugin->is = false;
            $this->plugin->task = [];
            return;
        }
        if(!$buyer instanceof Player or !$seller instanceof Player){
            FA::broadcast('플레이어가 탈주하여 경매가 종료되었습니다');
            $this->price = [];
            $this->item = [];
            $this->seller = [];
            $this->buyer = [];
            $this->plugin->is = false;
            $this->plugin->task = [];
            return;
        }
        $item = Item::jsonDeserialize($this->item);
        if(!$seller->getInventory()->contains($item)){
            FA::broadcast('판매자가 사기를 쳐 경매가 종료되었습니다');
            $this->price = [];
            $this->item = [];
            $this->seller = [];
            $this->buyer = [];
            $this->plugin->is = false;
            $this->plugin->task = [];
            return;
        }
        $eco = FA::$server->getPluginManager()->getPlugin('EconomyAPI');
        if($eco->myMoney($buyer) < $this->price){
            FA::broadcast('구매자가 사기를 쳐 경매가 종료되었습니다');
            $this->price = [];
            $this->item = [];
            $this->seller = [];
            $this->buyer = [];
            $this->plugin->is = false;
            $this->plugin->task = [];
            return;
        }
        $eco->reduceMoney($buyer, $this->price);
        $eco->addMoney($seller, $this->price);
        FA::broadcast($buyer->getName() . ' 님이 '. $this->price . '원으로 낙찰하였습니다!');
        $seller->getInventory()->removeItem($item);
        $buyer->getInventory()->addItem($item);
        $this->price = [];
        $this->item = [];
        $this->seller = [];
        $this->buyer = [];
        $this->plugin->is = false;
        $this->plugin->task = [];
        return;
    }
}
// fast access class
abstract class FA{
    public static $server;
    public static $prefix = '§e§l[ §f서버 §e] §r';
    public static function broadcast($message){
        return FA::$server->broadcastMessage(FA::$prefix . $message);
    }
}