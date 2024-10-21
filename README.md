# GTM_Checker_pl

## Skrypt do samodzielnej instalacji na serwerze, służący do sprawdzania poprawności wpięcia tagów GTM

Ponieważ miałem problem ze znalezieniam dobrego narzędzia, które szybko przeskanowałoby mi witrynę i sprawdziło, czy na wszystkich stronach mam wpięty kod Google Tag Managera i nie mogłem takiego narzędzia znaleźć, napisałem sobie skrypt, który to robi.

Co jest potrzebne? Własny serwer (ja używam Oracle Cloud Free Tier) z możliwością dostępu do [crona](https://pl.wikipedia.org/wiki/Cron) i nadawania uprawnień do plików.

Co robi skrypt? Podajemy mu domenę, a on skanuje kod strony głównej i znajduje na niej adresy URL podstron, do których prowadzą linki ze strony głównej. Powtarza to tak długo, na wszystkich znalezionych stronach, aż znajdzie wszystkie strony. Na każdej znalezionej stronie szuka tagów, a konkretnie dwóch fragmentów kodu:
```PHP
$gtm1 = 'www.googletagmanager.com/gtm.js';
$gtm2 = 'www.googletagmanager.com/ns.html';
```
Na podstawie tego co znajdzie, tworzy kilka plików:
```PHP
$ce = 'ce_website.pem'; - pobrany certyfikat ssl dla domeny
$list_to_check_file = 'urls.txt'; - roboczy plik, w którym przechowuje znalezione, ale jeszcze nie przeskanowane strony
$map_file = 'map.txt'; - lista wszystkich stron znalezionych w witrynie
$exc_file = 'exclude.txt'; - lista plików nie będących stronami, których rozszerzenia można sobie definiować w skrypcie
$hyperlinks_file = 'links.txt'; - lista połączeń pomiędzy stronami skąd => dokąd prowadzi link
$errors = 'errors.txt'; - tu zapisuje błędy, do ewentualnej diagnostyki
$finded = 'including_tags.txt'; - lista adresów url, na których znalazł oba tagi podane wyżej
$not_finded = 'excluding_tags.txt'; - lista adresów stron, na których nie znalazł co najmniej jednego z tagów podanych wyżej
$project_file = 'projekt.txt'; - roboczy plik, w którym zapisuje sobie własną nazwę projektu na czas pracy nad projektem
$sitemap = 'sitemap.xml'; - plik prostej mapy witryny w uniwersalnym formacie sitemap.xml
```

### Jak instaluje się skrypt?
Po pierwsze, należy zainstalować na serwerze [locrun - tu jest instrukcja](http://unixwiz.net/tools/lockrun.html)
To bardzo proste, ściągamy skrypt, komilujemy i wrzucamy na właściwe miejsce:
```console
$ wget http://unixwiz.net/tools/lockrun.c
$ gcc lockrun.c -o lockrun
$ sudo cp lockrun /usr/local/bin/
```
Locrun jest skryptem, który nie pozwoli, aby cron uruchomił jednocześnie dwa razy ten sam plik. Dlaczego to ważne? Ponieważ odpalony spider pracuje aż padnie, a co minutę cron próbuje uruchomić kolejną jego instancję, co może zatkać nasz serwer i spowodować wadliwe działanie skryptu. Można to zabezpieczyć inaczej, ale tutaj zdecydowałem się na locryn ze względu na prostotę.
Co dalej? Kopiujemy do wybranego katalogu skrypt spider.php, sprawdzamy właściciela i nadajemy mu uprawnienia 744.
Co trzeba ustawić w skrypcie: ścieżkę, na której znajduje się skrypt, UWAGA: folder, w którym znajduje się skrypt (w poniższym przypadku "html") musi mieć tego samego właściciela co nasz skrypt i uprawnienia 755
```PHP
$dir = '/home/www/jakisadres/html';
```
Nic więcej nie musimy ustawiać, chociaż można sobie pogrzebać w skrypcie i np. użyć go do szukania czegoś innego niż GTM. Dużo ustawień jest w zmiennych na początku skryptu, starałem się zachować dość jasne nazewnictow, bo komentarze dla odmiany są chaotyczne.
Aby skrypt działał dobrze musi się uruchamiać co minutę w cronie.
```console
* * * * * www-data /usr/local/bin/lockrun --lockfile=/home/www/jakisadres/spider.lockrun --quiet -- /home/www/jakiśadres/html/spider.php
```
Tutaj wyjaśniam "www-data" to użytkownik, który jest wspomnianym wcześniej właścicielem katalogu i pliku. Następnie jest ścieżka, pod którą skopiowaliśmy locruna, parametry locruna, w szczególności --lockfile=/home/www/jakisadres/spider.lockrun - to oznacza, że katalog /home/www/jakisadres/ musi mieć właściela i uprawnienia pozwalające na zapis pliku spider.lockrun (u mnie znów www-data i 755), i katalog, w którym znajduje się skrypt: /home/www/jakiśadres/html/spider.php. W powyższym wpisie skrypt próbuje uruchomić się co minutę.
To właściwie wszystko. Pozostaje nam uruchomić skrypt.
Tworzymy plik 'urls.txt' (ten sam właściel co skrypt i uprawnienia 644) i wpisujemy do niego domenę, którą chcemy skanować, w formacie `https://domena.pl/`
Domenę należy wpisać staranie i nie dodawać znaku końca linii.
Teraz zostało już tylko zmienić zawartość pliku on_off.txt z 0 na 1 (to jest wyłacznik działania skryptu, jeśli ustawimy 0, to nawet jeśli cron sprónuje skrypt uruchomić, to on nie będzie działać.
Należy poczekać po około minucie pojawi się folder (losowa trzydziestoznakowa nazwa), w którym znajdą się pliki opisane na początku. Kiedy skrypt skończy pracę (w zależności od liczby podstron od kilku do kilkudziesięciu minut) sam się wyłączy (zmieni zawartość pliku on_off.txt na 0 i posprząta pliki robocze.
Jeśli ktoś nie chce, albo nie może zainstalować skryptu, to [pod adresem https://metricsmaster.pl/](https://metricsmaster.pl/) znajdzie wersję skryptu z dodatkowym interfejsem, która pozwoli każdemu skorzystać ze skryptu.
Jeśli masz jakieś uwagi, [napisz do mnie marcin@kowol.pl](mailto:marcin@kowol.pl) - jeśli skrypt wygenerował plik errors.txt (lub inny o nazwie określonej w zmiennej $errors) to dołącz go do wiadomości, proszę.
