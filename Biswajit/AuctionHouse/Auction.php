<?php

declare(strict_types = 1);

namespace Biswajit\AuctionHouse;

use pocketmine\event\EventPriority;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\Server;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\plugin\PluginBase;
use CortexPE\Commando\PacketHooker;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

final class Auction extends PluginBase implements Listener{
   
    private static $instance;

    public function onEnable(): void
    {
        self::$instance = $this;
        $this->saveResource("config.yml");
        $this->saveResource("playerdata.yml");
        $this->saveResource("AuctionHouse.json");
        Server::getInstance()->getCommandMap()->register("FcAuction", new AuctionCommand());

        if (!PacketHooker::isRegistered()){
            PacketHooker::register($this);
        }
        
        if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }
        
        Server::getInstance()->getPluginManager()->registerEvent(InventoryTransactionEvent::class, function (InventoryTransactionEvent $event): void {
            $transaction = $event->getTransaction();
            $player = $transaction->getSource();

            foreach ($transaction->getActions() as $action) {
                $sourceItem = $action->getSourceItem();
                $targetItem = $action->getTargetItem();

                if (!is_null($targetItem->getNamedTag()->getTag("menu_item")) || !is_null($sourceItem->getNamedTag()->getTag("menu_item"))) {
                    $player->getInventory()->removeItem($sourceItem);
                    $player->getInventory()->removeItem($targetItem);
                }
            }
        }, EventPriority::MONITOR, Auction::getInstance());
    }
     
     public static function getInstance(): Auction
    {
    return self::$instance;
    }
  
    public function getAuctionHouseData(): Config
    {
        return new Config($this->getDataFolder() . "AuctionHouse.json", Config::JSON);
    }
    
    public function getData(): Config
    {
        return new Config($this->getDataFolder() . "playerdata.yml", Config::YAML, []);
    }
    
    public function arrayToPage(array $array, ?int $page, int $separator): array
    {
        $result = [];

        $pageMax = ceil(count($array) / $separator);
        $min = ($page * $separator) - $separator;

        $count = 1;
        $max = $min + $separator;

        foreach ($array as $item) {
            if ($count > $max) {
                continue;
            } else if ($count > $min) {
                $result[] = $item;
            }
            $count++;
        }
        return [$pageMax, $result];
    }

}
