<?php

/**
 
*███████╗██████╗░░█████╗░░██████╗████████╗
*██╔════╝██╔══██╗██╔══██╗██╔════╝╚══██╔══╝
*█████╗░░██████╔╝██║░░██║╚█████╗░░░░██║░░░
*██╔══╝░░██╔══██╗██║░░██║░╚═══██╗░░░██║░░░
*██║░░░░░██║░░██║╚█████╔╝██████╔╝░░░██║░░░
*╚═╝░░░░░╚═╝░░╚═╝░╚════╝░╚═════╝░░░░╚═╝░░░
 *This program is source code on minecraft frost creation server,
 * you can modify it or use it for personal use
 *
 * @author Biswajit
 * @link https://frostnetwork.xyz/
 */

namespace AuctionHouse\Biswajit;

use CortexPE\Commando\args\IntegerArgument;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\item\Item;
use CortexPE\Commando\BaseSubCommand;
use pocketmine\item\ItemBlock;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class SellSubCommand extends BaseSubCommand
{

    /**
     * @inheritDoc
     */
    protected function prepare(): void
    {
        $this->registerArgument(0, new IntegerArgument("price", true));
        $this->setPermission("FCAuctionHouse.cmd.ah");
    }

    public function onRun(CommandSender|Player $sender, string $aliasUsed, array $args): void
    {
        $config = Auction::getInstance()->getConfig();
        $file = Auction::getInstance()->getAuctionHouseData();
        $limit = $this->getLimit($sender, $config);

        if (!isset($args["price"])) {
            $sender->sendMessage($config->get("no-price"));
            return;
        }

        $price = intval($args["price"]);
        $item = $sender->getInventory()->getItemInHand();

        if ($item->equals(VanillaItems::AIR())) {
            $sender->sendMessage($config->get("air-item"));
            return;
        }

        while (true) {
            $id = random_int(1, 99999);

            if (!$file->exists($id)) {
                break;
            }
        }

        $lore = $config->get("lore");

        foreach ($lore as $key => $value) {
            $lore[$key] = str_replace(["{seller}", "{price}"], [$sender->getName(), $price], $value);
        }

        $item->setLore(array_merge($item->getLore(), $lore));
        $item->getNamedTag()->setString("seller", $sender->getName());
        $item->getNamedTag()->setString("seller_uuid", $sender->getPlayerInfo()->getUuid()->toString());
        $item->getNamedTag()->setInt("price", $price);
        $item->getNamedTag()->setInt("id", $id);

        if ($item instanceof ItemBlock) {
            $file->set($id, [serialize($item->nbtSerialize()), "itemBlock", $sender->getXuid()]);
        } else if ($item instanceof Item) {
            $file->set($id, [serialize($item->nbtSerialize()), "item", $sender->getXuid()]);
        }

        $sender->getInventory()->setItemInHand(VanillaItems::AIR());
        $sender->sendMessage(str_replace("{money}", $price, $config->get("item-sold")));

        $file->save();
        return;
    }

    private function getLimit(Player $player, Config $config): int
    {
        if (!$config->get("limit")) {
            return -1;
        }

        foreach ($config->get("permissions") as $key => $value) {
            if ($player->hasPermission($key)) {
                return $value;
            }
        }

        return $config->get("default-limit");
    }
}