<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $security
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ["onMenuConfigure", 100],
        ];
    }

    public function onMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        if (!$this->security->isGranted("IS_AUTHENTICATED_REMEMBERED")) {
            return;
        }

        $menu = $event->getMenu();

        $timeoffMenu = new MenuItemModel(
            "timeoff_menu",
            "Ferien & Krankheit",
            "timeoff_overview",
            [],
            "fas fa-umbrella-beach"
        );

        $timeoffMenu->addChild(
            new MenuItemModel("timeoff_overview", "Meine Übersicht", "timeoff_overview", [], "fas fa-calendar-alt")
        );

        $timeoffMenu->addChild(
            new MenuItemModel("timeoff_request", "Antrag stellen", "timeoff_request", [], "fas fa-plus-circle")
        );

        $timeoffMenu->addChild(
            new MenuItemModel("timeoff_approve", "Genehmigungen", "timeoff_approve", [], "fas fa-check-circle")
        );

        if ($this->security->isGranted("ROLE_ADMIN")) {
            $timeoffMenu->addChild(
                new MenuItemModel("timeoff_admin", "Verwaltung", "timeoff_admin", [], "fas fa-cog")
            );
        }

        $menu->addChild($timeoffMenu);
    }
}