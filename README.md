# LieTimeOffBundle (Kimai Plugin)

**Features (MVP):**
- Menü & Rechte (PrependExtensionInterface), UI-Seiten `/timeoff`
- Liechtenstein-Feiertage (Service) inkl. bewegliche Tage
- Urlaubszählung: Arbeitstage minus Feiertage (5/6-Tage-Woche)
- Upfront-Entitlement (Pro-Rata) inkl. Jugendbonus & FTE
- Konsolenbefehl: `timeoff:init-year <YEAR>`

**Installation:**
1. Entpacken nach: `var/plugins/LieTimeOffBundle/`
2. `bin/console cache:clear --env=prod && bin/console kimai:reload --env=prod`
3. Rechte zuweisen (Benutzer & Rollen):
   - Mitarbeitende: `timeoff_li_request`
   - Teamlead: `timeoff_li_approve`
   - HR/Admin: `timeoff_li_manage`, `timeoff_li_view_all`
4. Aufruf im Browser: `/timeoff`

> Hinweis: Dies ist ein Grund-Plugin ohne DB-Entities/Forms. Für produktive Nutzung:
> - Entities (LeavePolicy/EmployeePolicy/LeaveBalance/LeaveRequest) + Migrations addieren
> - Request-/Approve-Formulare und Workflow implementieren
> - Monatsabschluss + PDF/ICS ergänzen
