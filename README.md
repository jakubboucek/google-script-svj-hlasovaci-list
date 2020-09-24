# Hlasovací list pro SVJ

## 1. část: Google Script

Script poskytuje nástroje pro snadnější zapisování hlasování sa shromážděních (nejen) SVJ.

**[Vzor hlasovací Google Tabulky](https://drive.google.com/open?id=1B-ZZJACx0LEyTDjiirESIzYHbXZ3JUBwl1MVAQ3CaZY)**
 
### Instalace
1. Zkopírujte si [vzorovou tabulku](https://docs.google.com/spreadsheets/d/1d2ZNhql1YX0-JHWkUGoWebvTIq7r9fQbpZRXWsXlzGU/copy) 
2. Volbou **Nástroje > Editor scriptů** si vytvořte Google Script připojený k tabulce 
([více informací](https://developers.google.com/apps-script/guides/bound))
3. Do scriptu vložte obsah souborů z [google-script složky](google-script) a uložte jej kliknutím na ikonu diskety.

### Odinstalace
Tabulku smažte.

### GDPR
Pokud máte Google účet v rámci G Suite (tedy firemní účet), pak tento splňuje všechny požadavky GDPR. Pokud používáte
bezplatný účet (gmail.com), tak u toho Google negarantuje soulad s GDPR.

Přestože v systému vedete údaje, které jsou veřejné v katastru, tak i tak se jedná o osobní údaje a musí se s nimi
nakládat odpovídajícícm způsobem (tedy subjekty mají právo vědět, kde a jak jsou jejich údaje zpracovávané a mělo by to
být uvedeno ve vašich směrnicích).

## 2. část: Stahovač detailů o bytových jednotkách z Katastru (PHP)

Script stáhne ze systému [Nahlížení do katastru nemovitostí](https://nahlizenidokn.cuzk.cz/) informace o jednotkách
a připraví data pro import do Prezenční listiny projektu Hlasovacího listu.

Informace o použití scriptu jsou uvedeny v těle scriptu [`php-katastr-grabber/katastr.php`](php-katastr-grabber/katastr.php).


## Známé problémy
*Žádné zatím nejsou, sledujte [Issues](https://github.com/jakubboucek/google-script-svj-hlasovaci-list/issues)*
