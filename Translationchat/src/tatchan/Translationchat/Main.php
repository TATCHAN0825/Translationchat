<?php


namespace tatchan\Translationchat;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener
{


	public function onEnable(): void
	{
		$this->getLogger()->info("§6翻訳チャットプラグインを起動しました");
		$this->getLogger()->info("§aMake By tatchan");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->setting = new Config($this->getDataFolder() . "setting.yml", Config::YAML, array(
			"target" => "ja",
			"api" => "AKfycbzNNISCTqAW1tsY91tYYi7DdBhPleP0WmxmKz71WA3jfoi1fcM",
		));
		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		switch ($command->getName()) {
			case "translation":
				$bool = $this->config->get($sender->getName());
				$bool = !$bool;
				if ($bool === true) {
					$sender->sendMessage("§a[翻訳チャット]翻訳モードをONにしました");
					$this->config->set($sender->getName(), true);
					$this->config->save();
				} elseif ($bool === false) {
					$sender->sendMessage("§a[翻訳チャット]翻訳モードをOFFにします");
					$this->config->set($sender->getName(), false);
					$this->config->save();
				}
				return true;
			default:
				return false;
		}
	}

	public function onChat(PlayerChatEvent $event)
	{

		$player = $event->getPlayer();
		if ($this->config->get($player->getName()) == true) {
			$msg = $event->getMessage();
			$target = $this->setting->get("target");
			$api = $this->setting->get("api");
			$this->getServer()->getAsyncPool()->submitTask(new AsyncTest($player, $api, $msg, $target));
		}
	}

	public function onJoin(PlayerJoinEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		if ($this->config->exists($name) === false) {
			$this->config->set($name, false);
			$this->config->save();
		}
	}

}

class AsyncTest extends AsyncTask
{

	private $url, $text, $target;

	public function __construct(Player $player, String $api, String $text, String $target)
	{
		$this->url = "https://script.google.com/macros/s/$api/exec?text=$text&source=&target=$target";
		$this->text = $text;
		$this->target = $target;
		$this->storeLocal($player);
	}

	public function onRun()
	{
		$options = array(
			'http' => array('ignore_errors' => true)
		);
		$context = stream_context_create($options);

		$data = file_get_contents($this->url, false, $context);
		if ($data) {
			$pattern = '#\AHTTP/\d+\.\d+ (\d+) (.*)\E#';
			preg_match($pattern, $http_response_header[0], $matches);
			if (isset($matches[1])) $status = (int)$matches[1];
		}
		$data = json_decode($data);
		if (isset($data->text)) {
			$array = array("text" => "$data->text", "code" => $data->code, "status" => $status);
			$this->setResult($array);
		}

	}

	public function onCompletion(Server $server)
	{ //onRun処理が終わったら実行されます(無くても良い)
//継承したサーバーインスタンスの使用ができます
		$d = $this->getResult();
		if ($d["status"] === 302 && $d["code"] === 200) {
			$player = $this->fetchLocal();
			$name = $player->getName();
			$text = $d["text"];
			$server->broadcastMessage("§a[翻訳チャット]$name:$text");
		} else {
			$player = $this->fetchLocal();
			$player->sendMessage("§c⚠[翻訳チャット]エラー");
		}
	}
}

