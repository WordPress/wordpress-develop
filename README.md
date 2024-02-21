# WordPress

WordPress development repository'ye hoş geldiniz! Lütfen hata raporları açma, yama gönderme, değişiklikleri test etme, belge yazma veya herhangi bir şekilde katkıda bulunma hakkında bilgi almak için [katkıda bulunucu el kitabını](https://make.wordpress.org/core/handbook/) inceleyin.

## İçindekiler

- [Başlarken](#başlarken)
- [GitHub Codespaces Kullanma](#github-codespaces-kullanma)
- [Yerel Geliştirme](#yerel-geliştirme)
- [Geliştirme Ortamı Komutları](#geliştirme-ortamı-komutları)
- [Apple Silicone makineleri ve eski MySQL sürümleri](#apple-silicone-makineleri-ve-eski-mysql-sürümleri)
- [Kimlik Bilgileri](#kimlik-bilgileri)
- [Notlar](#notlar)

## Başlarken

### GitHub Codespaces Kullanma

Bu depo için bir kod alanı oluşturmak için [buraya tıklayın](https://github.com/codespaces/new?hide_repo_select=true&ref=trunk&repo=75645659). Kod alanı, Visual Studio Code'un web tabanlı bir sürümünde açılacaktır. [dev container](.devcontainer/devcontainer.json), bu projede gereken yazılımlarla tam olarak yapılandırılmıştır.

**Not**: Dev konteynerler, [GitHub Codespaces](https://github.com/codespaces) ve [diğer araçlar](https://containers.dev/supporting) tarafından desteklenen bir açık belirlemedir.

Bazı tarayıcılarda komut paletini açma klavye kısayolu (Ctrl/Command + Shift + P) maya çakışabilir. Komut paletini açmak için `F1` tuşunu veya editörün alt sol köşesindeki dişli simgesini kullanabilirsiniz.

Kod alanınızı açarken, WordPress kurulumunuzun başarıyla yapılandırıldığından emin olmak için `postCreateCommand`'in çalışmasını bekleyin. Bu birkaç dakika sürebilir.

### Yerel Geliştirme

WordPress, PHP, MySQL ve JavaScript tabanlı bir projedir ve JavaScript bağımlılıkları için Node'u kullanır. Hızlı bir şekilde başlamak için yerel bir geliştirme ortamı kullanılabilir.

Bilgisayarınızda komut satırını nasıl kullanacağınıza dair temel bir anlayışa ihtiyacınız olacak. Bu, yerel geliştirme ortamını kurmanıza, başlatmanıza ve gerektiğinde durdurmanıza, ve testleri çalıştırmanıza olanak tanır.

Bilgisayarınızda Node ve npm yüklü olmalıdır. Node, geliştirici araçları için kullanılan bir JavaScript çalıştırma ortamıdır ve npm, Node ile birlikte gelen paket yöneticisidir. İşletim sisteminiz için bir paket yöneticisi yüklüyse, kurulum şu kadar basit olabilir:

* macOS: `brew install node`
* Windows: `choco install nodejs`
* Ubuntu: `apt install nodejs npm`

Paket yöneticisi kullanmıyorsanız, [Node.js indirme sayfasına](https://nodejs.org/en/download/) giderek yükleyicileri ve ikili dosyaları bulabilirsiniz.

**Not:** WordPress şu anda resmi olarak sadece Node.js `20.x` ve npm `10.x`'i desteklemektedir.

Ayrıca bilgisayarınızda [Docker](https://www.docker.com/products/docker-desktop) yüklü ve çalışır durumda olmalıdır. Docker, yerel geliştirme ortamını destekleyen sanallaştırma yazılımıdır. Docker, diğer normal bir uygulama gibi yüklenebilir.

### Geliştirme Ortamı Komutları

Bu komutları kullanmadan önce [Docker](https://www.docker.com/products/docker-desktop)'ın çalıştığından emin olun.

#### Geliştirme ortamını ilk kez başlatmak için

Clone the current repository using `git clone https://github.com/WordPress/wordpress-develop.git`. Then in your terminal move to the repository folder `cd wordpress-develop` and run the following commands:

```bash
npm install
npm run build:dev
npm run env:start
npm run env:install

markdown
Copy code
Your WordPress site will be accessible at http://localhost:8889. You can see or change configurations in the `.env` file located at the root of the project directory.

#### Değişiklikleri izlemek için

Eğer WordPress çekirdek dosyalarında değişiklik yapıyorsanız, dosya izleyiciyi başlatmalısınız:

```bash
npm run dev
Izleyiciyi durdurmak için  tuşlarına basın.ctrl+c

WP-CLI komutu çalıştırmak için
bash
Copy code
npm run env:cli -- <komut>
WP-CLI'nin çeşitli kullanışlı komutları vardır. Dokümantasyonda  komutunu kullanmanız gerektiğinde,  kullanmalısınız. Örneğin:wpnpm run env:cli --

bash
Copy code
npm run env:cli -- help
Testleri çalıştırmak için
Bu komutlar PHP


```markdown
run test:e2e
```

PHP testlerine ek parametreler eklemek için `--` ve ardından [komut satırı seçenekleri](https://docs.phpunit.de/en/10.4/textui.html#command-line-options) ekleyebilirsiniz:

```bash
npm run test:php -- --filter <test adı>
npm run test:php -- --group <grup adı veya bilet numarası>
```

#### Geliştirme ortamını yeniden başlatmak için

`docker-compose.yml` veya `.env` dosyalarındaki yapılandırmalarda değişiklik yaptıysanız, ortamı yeniden başlatmak isteyebilirsiniz:

```bash
npm run env:restart
```

#### Geliştirme ortamını durdurmak için

Ortamı kullanmadığınızda bilgisayarınızın gücünü ve kaynaklarını korumak için ortamı durdurabilirsiniz:

```bash
npm run env:stop
```

#### Geliştirme ortamını tekrar başlatmak için

Ortamı tekrar başlatmak için tek bir komut:

```bash
npm run env:start
```

#### Geliştirme ortamını sıfırlama

Geliştirme ortamı sıfırlanabilir. Bu, veritabanını yok eder ve çekilen Docker görüntülerini kaldırmaya çalışır.

```bash
npm run env:reset
```

### Apple Silicone makineleri ve eski MySQL sürümleri

MySQL Docker görüntüleri, MySQL sürümleri 5.7 ve önceki sürümler için Apple Silicone işlemcilerini (M1, M2, vb.) desteklemez.

Apple Silicone makinesinde MySQL <= 5.7 kullanırken, şu içeriğe sahip bir `docker-compose.override.yml` dosyası oluşturmalısınız:

```yaml
services:

  mysql:
    platform: linux/amd64
```

Ayrıca, bu geçici çözüm için Docker'da "Apple Silicon üzerinde x86/AMD64 emülasyonu için Rosetta'yı kullan" ayarı devre dışı bırakılmalıdır.

## Kimlik Bilgileri

Bu projenin varsayılan çevresel kimlik bilgileri şunlardır:

- **Veritabanı Adı:** `wordpress_develop`
- **Kullanıcı Adı:** `root`
- **Şifre:** `password`

Sitenize giriş yapmak için http://localhost:8889 adresine gidin.

- **Kullanıcı Adı:** `admin`
- **Şifre:** `password`

**Not**: Codespaces ile kullanıyorsanız, terminaldeki portlar sekmesinden port yönlendirmesi yapılan URL'yi açın ve siteye giriş yapmak için `/wp-admin` ekleyin.

Yeni bir şifre oluşturmak için (önerilen):

1. Gösterge Tablosuna gidin
2. Soldaki Kullanıcılar menüsüne tıklayın
3. Admin kullanıcısı altındaki Düzenle bağlantısına tıklayın
4. Aşağı kaydırın ve 'Şifre Oluştur' seçeneğine tıklayın. Oluşturulan şifreyi kullanabilir veya değiştirebilir, ardından 'Kullanıcıyı Güncelle'ye tıklayın. Oluşturulan şifreyi kullanıyorsanız, bir yerde kaydetmeyi unutmayın (şifre yöneticisi, vb.).

## Notlar

Bu README dosyası, WordPress geliştirme ortamını başlatmak, testleri çalıştırmak ve diğer işlemleri gerçekleştirmek için kullanılabilecek temel komutları içermektedir. Daha fazla bilgi için [katkıda bulunucu el kitabını](https://make.wordpress.org/core/handbook/) inceleyin.
```