<?php

declare(strict_types=1);

namespace DiamondStrider1\Remark\Command;

use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;

/**
 * Keeps track of overloads on a BoundCommand.
 */
final class OverloadMap
{
    /** @var CommandOverload[] */
    private array $overloads = [];
    private string $usage = '';

    public function __construct(
        private string $name,
    ) {
    }

    /**
     * @param string[] $subNames
     */
    public function add(array $subNames, HandlerMethod $handlerMethod): void
    {
        if (0 !== strlen($this->usage)) {
            $this->usage .= ' OR ';
        }
        $this->usage .= "/$this->name ";
        $subNamesString = implode(' ', $subNames);
        if (strlen($subNamesString) > 0) {
            $this->usage .= "$subNamesString ";
        }

        $parameters = [];
        foreach ($subNames as $index => $subName) {
            $parameters[] = CommandParameter::enum(
                $subName,
                new CommandEnum("param-$index-$subName", [$subName]),
                CommandParameter::FLAG_FORCE_COLLAPSE_ENUM,
            );
        }

        foreach ($handlerMethod->getArgs() as $name => $arg) {
            $usageComponent = $arg->toUsageComponent($name);
            if (null !== $usageComponent) {
                $this->usage .= "$usageComponent ";
            }
            $parameter = $arg->toCommandParameter($name);
            if (null !== $parameter) {
                $parameters[] = $parameter;
            }
        }
        $this->usage = rtrim($this->usage);
        $this->overloads[] = new CommandOverload(false, $parameters);
    }

    /**
     * @return CommandParameter[][]
     */
    public function getOverloads(): array
    {
        return $this->overloads;
    }

    public function getUsage(): string
    {
        return $this->usage;
    }
}
