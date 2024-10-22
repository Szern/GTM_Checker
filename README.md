# GTM_Checker

## Skrypt do samodzielnej instalacji na serwerze, służący do sprawdzania poprawności wpięcia tagów GTM

Ponieważ miałem problem ze znalezieniam dobrego narzędzia, które szybko przeskanowałoby mi witrynę i sprawdziło, czy na wszystkich stronach mam wpięty kod Google Tag Managera i nie mogłem takiego narzędzia znaleźć, napisałem sobie skrypt, który to robi.

Jest to narzędzie do sprawdzania obecności tagów Google Tag Managera na wszystkich podstronach w domenie (skrypt w obecnej wersji nie sprawdza subdomen, trzeba je sprawdzać oddzielnie).

Narzędzie posługuje się jedynie ogólniedostępnymi danymi, które można pobrać ze strony znajdującej się w danej domenie. Narzędzie nie zbiera, ani nie przechowuje żadnych danych o użytkowniku, nie używa również powszechnie dostępnych narzędzi analitycznych.

**Co jest potrzebne?** Własny serwer (ja używam Oracle Cloud Free Tier) z możliwością dostępu do [crona](https://pl.wikipedia.org/wiki/Cron) i nadawania uprawnień do plików.

**Co robi skrypt?** Podajemy mu domenę, a on skanuje kod strony głównej i znajduje na niej adresy URL podstron, do których prowadzą linki ze strony głównej. Powtarza to tak długo, na wszystkich znalezionych stronach, aż znajdzie wszystkie strony. Na każdej znalezionej stronie szuka tagów, a konkretnie dwóch fragmentów kodu:
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
Locrun jest skryptem, który nie pozwoli, aby cron uruchomił jednocześnie dwa razy ten sam plik. Dlaczego to ważne? Ponieważ odpalony spider pracuje aż padnie, a co minutę cron próbuje uruchomić kolejną jego instancję, co może zatkać nasz serwer i spowodować wadliwe działanie skryptu. Można to zabezpieczyć inaczej, ale tutaj zdecydowałem się na locrun ze względu na prostotę. W kolejnej wersji skryptu, nad którą pracuję, locrun nie będzie już potrzebny.
**Co dalej?** Kopiujemy do wybranego katalogu skrypt spider.php, sprawdzamy właściciela i nadajemy mu uprawnienia 744.
Co trzeba ustawić w skrypcie: ścieżkę, na której znajduje się skrypt, UWAGA: folder, w którym znajduje się skrypt (w poniższym przypadku "html") musi mieć tego samego właściciela co nasz skrypt i uprawnienia 755
```PHP
$dir = '/home/www/jakisadres/html';
```
Nic więcej nie musimy ustawiać, chociaż można sobie pogrzebać w skrypcie i np. użyć go do szukania czegoś innego niż GTM. Dużo ustawień jest w zmiennych na początku skryptu, starałem się zachować dość jasne nazewnictwo, bo komentarze dla odmiany są chaotyczne.
Aby skrypt działał dobrze, musi się uruchamiać co minutę w cronie.
```console
* * * * * www-data /usr/local/bin/lockrun --lockfile=/home/www/jakisadres/spider.lockrun --quiet -- /home/www/jakiśadres/html/spider.php
```
Tutaj wyjaśniam "www-data" to użytkownik, który jest wspomnianym wcześniej właścicielem katalogu i pliku. Następnie jest ścieżka, pod którą skopiowaliśmy locruna, parametry locruna, w szczególności `--lockfile=/home/www/jakisadres/spider.lockrun` - to oznacza, że katalog `/home/www/jakisadres/` musi mieć właściela i uprawnienia pozwalające na zapis pliku `spider.lockrun` (u mnie znów www-data i 755), i katalog, w którym znajduje się skrypt: `/home/www/jakiśadres/html/spider.php`. W powyższym wpisie skrypt próbuje uruchomić się co minutę.
To właściwie wszystko. **Pozostaje nam uruchomić skrypt.**

Tworzymy plik 'urls.txt' (ten sam właściel co skrypt i uprawnienia 644) i wpisujemy do niego domenę, którą chcemy skanować, w formacie `https://domena.pl/`. Domenę należy wpisać staranie i nie dodawać znaku końca linii.
Teraz zostało już tylko zmienić zawartość pliku `on_off.txt` z 0 na 1 (to jest wyłacznik działania skryptu, jeśli ustawimy 0, to nawet jeśli cron sprónuje skrypt uruchomić, to on nie będzie działać).

Należy poczekać, po około minucie (kolejne uruchomienie skryptu z crona) pojawi się folder (losowa trzydziestoznakowa nazwa), w którym znajdą się pliki opisane na początku. Kiedy skrypt skończy pracę (w zależności od liczby podstron od kilku do kilkudziesięciu minut) sam się wyłączy (zmieni zawartość pliku `on_off.txt` na 0 i posprząta pliki robocze.

Jeśli ktoś nie chce, albo nie może zainstalować skryptu, to [pod adresem https://metricsmaster.pl/](https://metricsmaster.pl/) znajdzie wersję skryptu z dodatkowym interfejsem, która pozwoli każdemu skorzystać ze skryptu bez jego instalacji, bez żadnych oraniczeń.

Jeśli masz jakieś uwagi, [napisz do mnie marcin@kowol.pl](mailto:marcin@kowol.pl) - jeśli skrypt wygenerował plik errors.txt (lub inny o nazwie określonej w zmiennej $errors) to dołącz go do wiadomości, proszę.

---

## The script for self-installation on the server, used to check the correctness of GTM tags

Because I had trouble finding a good tool that would quickly scan my website and check whether I had the Google Tag Manager code on all pages and I couldn't find such a tool, I wrote a script that does it.

**What is needed?** Your own server (I use Oracle Cloud Free Tier) with the ability to access cron and assign file permissions.

**What does the script do?** We give him the domain and he scans the code of the home page and finds the URLs of subpages to which links from the home page lead. It repeats this on all found pages until it finds all pages. On each page it finds, it looks for tags, specifically two pieces of code:
```PHP
$gtm1 = 'www.googletagmanager.com/gtm.js';
$gtm2 = 'www.googletagmanager.com/ns.html';
```
Based on what it finds, it creates several files:
```PHP
ce = 'ce_website.pem'; - downloaded SSL certificate for the domain
$list_to_check_file = 'urls.txt'; - working file where it stores found but not yet scanned pages
$map_file = 'map.txt'; - list of all pages found on the site
$exc_file = 'exclude.txt'; - list of non-page files whose extensions can be defined in the script
$hyperlinks_file = 'links.txt'; - list of connections between pages from where = where the link leads
$errors = 'errors.txt'; - saves errors here for possible diagnostics
$finded = 'including_tags.txt'; - list of url addresses where he found both tags given above
$not_finded = 'excluding_tags.txt'; - list of website addresses where he did not find at least one of the tags given above
$project_file = 'project.txt'; - a working file in which I save my own project name while working on the project
$sitemap = 'sitemap.xml'; - a simple sitemap file in the universal sitemap.xml format
```
### How do I install the script?

First, install locrun on the server - [here are the instructions](http://unixwiz.net/tools/lockrun.html). It's very simple, download the script, compile it and put it in the right place:
```console
$ wget http://unixwiz.net/tools/lockrun.c
$ gcc lockrun.c -o lockrun
$ sudo cp lockrun /usr/local/bin/
```
Locrun is a script that will not allow cron to run the same file twice at the same time. Why is it important? Because a running spider works until it dies, and every minute cron tries to launch another instance of it, which may clog our server and cause the script to malfunction. You can secure it differently, but here I chose locryn because of its simplicity.
**What's next?** We copy the spider.php script to the selected directory, check the owner and give it 744 permissions. What needs to be set in the script: the path where the script is located, NOTE: the folder in which the script is located (in the case below 'html') must have the same owner as our script and permissions 755.
```PHP
$dir = '/home/www/anypath/html';
```
We don't have to set anything else, although you can dig into the script and, for example, use it to look for something other than GTM. A lot of settings are in variables at the beginning of the script, I tried to keep the names quite clear, because the comments are chaotic for a change. For the script to work properly, it must run in cron every minute.
```console
* * * * * www-data /usr/local/bin/lockrun --lockfile=/home/www/anypath/spider.lockrun --quiet -- /home/www/anypath/html/spider.php
```
Here I explain 'www-data' is the user who is the owner of the directory and file mentioned earlier. Then there is the path where we copied locrun, the locrun parameters, in particular `--lockfile=/home/www/jakisadres/spider.lockrun` - this means that the `/home/www/anypath/` directory must have the owner and permissions to save the file `spider.lockrun` (in my case www-data and 755 again), and the directory where the script is located: `/home/www/anypath/html/spider.php`. In the above entry, the script tries to run every minute. That's pretty much it. **All we have to do is run the script**.

We create the `urls.txt` file (the same owner as the script and permissions 644) and enter the domain we want to scan in the format `https://domain.pl/`. The domain should be entered carefully and do not add a line break character. Now all that's left is to change the content of the `on_off.txt` file from 0 to 1 (this is the script's operation switch; if we set 0, even if cron tries to run the script, it will not work.

Wait about a minute and a folder will appear (a random thirty-character name), which will contain the files described at the beginning. When the script finishes working (depending on the number of subpages, from several to several dozen minutes), it will turn off itself (change the content of the `on_off.txt` file to 0 and clean up the working files.

If someone does not want to, or cannot install the script, at [https://gtmchecker.metricsmaster.eu/](https://gtmchecker.metricsmaster.eu/) he will find a version of the script with an additional interface that will allow anyone to use the script.

If you have any comments, [please write to me marcin@kowol.pl](mailto:marcin@kowol.pl) - if the script generated an errors .txt (or another name specified in the $errors variable) then include it in the message, please.
