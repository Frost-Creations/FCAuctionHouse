<?php /** @noinspection PhpDeprecationInspection */

namespace Biswajit\AuctionHouse;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\IRunnable;
use muqsit\invmenu\InvMenu;
use davidglitch04\libEco\libEco;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\StainedGlassPane;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use AuctionHouse\Biswajit\Auction;
use pocketmine\item\ItemFactory;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NbtStreamReader;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class AuctionCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct(Auction::getInstance(), "market", "Open market", ["auctionhouse", "hdv", "ah"]);
        $this->setPermission("FCAuctionHouse.cmd.ah");
    }

    protected function prepare(): void
    {
        $this->registerSubCommand(new SellSubCommand(Auction::getInstance(), "sell", "Sell items", []));
        $this->setPermission("FCAuctionHouse.cmd.ah");
    }

    public function onRun(Player|CommandSender $sender, string $aliasUsed, array $args): void
    {
        $config = Auction::getInstance()->getConfig();
        $data = Auction::getInstance()->getData();
        $file = Auction::getInstance()->getAuctionHouseData();

        if (!$sender instanceof Player) {
            $sender->sendMessage($config->get("not-player"));
            return;
        } else if ($sender->getGamemode() === GameMode::SPECTATOR()) {
            $sender->sendMessage($config->get("spectator-player"));
            return;
        }

        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $menu->setName($config->get("menu-name"));

        $page = 1;

        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($menu, $page, $config): void {
            $player = $transaction->getPlayer();
            $item = $transaction->getItemClicked();

            if (is_null($item->getNamedTag()->getTag("id"))) {
                $page = $menu->getInventory()->getItem(45)->getCount();

                if ($item->getCustomName() === $config->get("next-name")) {
                    $this->addAuctionHouseItems($menu, ($page + 1));
                } else if ($item->getCustomName() === $config->get("previous-name") && $page > 1) {
                    $this->addAuctionHouseItems($menu, ($page - 1));
                } else if ($item->getCustomName() === $config->get("refresh-name")) {
                    $this->addAuctionHouseItems($menu, $page);
                } else if ($item->getCustomName() === $config->get("me-name")) {
                    $data = Auction::getInstance()->getData();
                    if ($data->exists(strtolower($player->getName()))) {
                        $money = $data->get(strtolower($player->getName()));
                        if($money > 0){
                        libEco::addMoney($player, $money);
                        $data->set(strtolower($player->getName()), 0);
                        $data->save();
                        $player->removeCurrentWindow();
                        $player->sendMessage("§eYou Receive §7" . $money . " §eFrom Auction House!");
                      }else{
                       $player->removeCurrentWindow();
                       $player->sendMessage("§eYou Dont Have Any Offline Money To Claim!");
                      }
  	             }
                }
                return;
            }

            $this->confirm($player, $item, 0);
        }));

        $this->addAuctionHouseItems($menu, $page);
        $menu->send($sender);
    }

    private function addAuctionHouseItems(InvMenu $menu, int $page)
    {
        $file = Auction::getInstance()->getAuctionHouseData();
        $config = Auction::getInstance()->getConfig();

        $menu->getInventory()->clearAll();

        foreach (Auction::getInstance()->arrayToPage(array_reverse($file->getAll()), $page, 45)[1] as $value) {
            if ($value[1] === "itemBlock") {
                $item = ItemBlock::nbtDeserialize(unserialize($value[0]));
                $item->getNamedTag()->setInt("menu_item", 0);
                $menu->getInventory()->addItem($item);
            } else {
                $item = Item::nbtDeserialize(unserialize($value[0]));
                $item->getNamedTag()->setInt("menu_item", 0);
                $menu->getInventory()->addItem($item);
            }
        }

        $item = StringToItemParser::getInstance()->parse($config->get("actual-id"))->setCount($page)->setCustomName($config->get("actual-name"));
        $menu->getInventory()->setItem(45, $item);

        $item = StringToItemParser::getInstance()->parse($config->get("previous-id"))->setCustomName($config->get("previous-name"));
        $menu->getInventory()->setItem(48, $item);

        $item = StringToItemParser::getInstance()->parse($config->get("refresh-id"))->setCustomName($config->get("refresh-name"));
        $menu->getInventory()->setItem(49, $item);

        $item = StringToItemParser::getInstance()->parse($config->get("next-id"))->setCustomName($config->get("next-name"));
        $menu->getInventory()->setItem(50, $item);

        $item = StringToItemParser::getInstance()->parse($config->get("me-id"))->setCustomName($config->get("me-name"));
        $menu->getInventory()->setItem(53, $item);
    }

    private function myItems(Player $player): void
    {
        $config = Auction::getInstance()->getConfig();

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName($config->get("menu-name"));

        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction): void {
            $player = $transaction->getPlayer();
            $item = $transaction->getItemClicked();

            $this->confirm($player, $item, 1);
        }));

        foreach ($this->getAuctionHousePlayerItems($player) as $value) {
            if ($value[1] === "itemBlock") {

                $item = ItemBlock::nbtDeserialize(unserialize($value[0]));
                $item->getNamedTag()->setInt("menu_item", 0);
                $menu->getInventory()->addItem($item);
            } else {
                $item = Item::nbtDeserialize(unserialize($value[0]));
                $item->getNamedTag()->setInt("menu_item", 0);
                $menu->getInventory()->addItem($item);
            }
        }

        $menu->send($player);
    }

    private function confirm(Player $player, Item $item, int $type): void
    {
        $config = Auction::getInstance()->getConfig();

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $menu->setName($config->get("menu-name"));

        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($item, $type, $config): void {
            $player = $transaction->getPlayer();

            if ($transaction->getItemClicked()->getCustomName() === $config->get("confirm-name")) {
                $this->checkAuctionHouse($player, $item, $type);
            }

            $player->removeCurrentWindow();
        }));

        $confirm = StringToItemParser::getInstance()->parse($config->get("confirm-id"))->setCustomName($config->get("confirm-name"))->getBlock();
        $cancel = StringToItemParser::getInstance()->parse($config->get("cancel-id"))->setCustomName($config->get("cancel-name"))->getBlock();

        foreach ([0, 1, 2, 3, 9, 10, 11, 12, 18, 19, 20, 21] as $slot) {
            if($confirm instanceof (VanillaBlocks::STAINED_GLASS_PANE())) {
                $menu->getInventory()->setItem($slot, $confirm->setColor(DyeColor::GREEN())->asItem()->setCustomName($config->get("confirm-name")));
            }else{
                $menu->getInventory()->setItem($slot, $confirm);
            }
        }

        foreach ([5, 6, 7, 8, 14, 15, 16, 17, 23, 24, 25, 26] as $slot) {
            if($cancel instanceof (VanillaBlocks::STAINED_GLASS_PANE())) {
                $menu->getInventory()->setItem($slot, $cancel->setColor(DyeColor::RED())->asItem()->setCustomName($config->get("cancel-name")));
            }else{
                $menu->getInventory()->setItem($slot, $cancel);
            }
        }

        $item->getNamedTag()->setInt("menu_item", 0);
        $menu->getInventory()->setItem(13, $item);

        $menu->send($player);
    }

    private function checkAuctionHouse(Player $player, Item $item, int $type): void
    {
        $file = Auction::getInstance()->getAuctionHouseData();
        $config = Auction::getInstance()->getConfig();
        $data = Auction::getInstance()->getData();
        $name = $player->getName();

        if (is_null($item->getNamedTag()->getTag("id")) || is_null($item->getNamedTag()->getTag("price"))) {
            return;
        }

        $price = $item->getNamedTag()->getInt("price");
        $id = $item->getNamedTag()->getInt("id");
        $seller = strtolower($item->getNamedTag()->getString("seller"));
        $sellerUuid = $item->getNamedTag()->getString("seller_uuid");
       
      $money = libEco::myMoney($player, function(float $money) use ($player, $price, $item, $type, $id): void {
        if ($price > $money && $type === 0) {
            $player->sendMessage($config->get("no-money"));
            return;
        } else if (!$player->getInventory()->canAddItem($item)) {
            $player->sendMessage($config->get("full-inventory"));
            return;
        } else if (!$file->exists($id)) {
            $player->sendMessage($config->get("item-already-purchased"));
            return;
        });
    }
        if ($type === 0) {
            $target = Server::getInstance()->getPlayerExact($seller);
            $_price = floor($price * (1 - intval($config->get("tax")) / 100));

            if ($target instanceof Player) {
                $target->sendMessage($config->get("player-bought"));
            }

            if ($p = Server::getInstance()->getPlayerExact($seller)){
                libEco::addMoney($p, $_price);
            }else{
                if ($data->exists($seller)) {
                   $data->set($seller, $data->get($seller) + $_price);
                   $data->save();
                   } else {
                   $data->set($seller, $_price);
                   $data->save();
                  }
            }
            libEco::reduceMoney($player, $_price, function() : void {});
        }

        $item->getNamedTag()->removeTag("price");
        $item->getNamedTag()->removeTag("id");
        $item->getNamedTag()->removeTag("seller");
        $sellerUuid = $item->getNamedTag()->getString("seller_uuid");
        $item->getNamedTag()->removeTag("menu_item");

        if (($lore = $item->getLore()) >= count($config->get("lore"))) {
            $item->setLore(array_splice($lore, 0, -count($config->get("lore"))));
        }

        $player->getInventory()->addItem($item);

        $file->remove($id);
        $file->save();

        if ($type === 0) {
            $player->sendMessage(str_replace("{money}", $price, $config->get("item-purchased")));
        } else if ($type === 1) {
            $player->sendMessage($config->get("item-deleted"));
        }
    }
}
