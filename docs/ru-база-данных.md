# База данных — PostgreSQL + Doctrine

## Стек

- **СУБД:** PostgreSQL 16
- **ORM:** Doctrine ORM 3.6
- **Миграции:** Doctrine Migrations

## Подключение

Строка подключения задаётся в `.env`:

```dotenv
DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app?serverVersion=16&charset=utf8"
```

> Хост `database` — это имя Docker-сервиса внутри Docker-сети. Снаружи контейнера используйте `localhost:5432`.

Настройки Doctrine: `config/packages/doctrine.yaml`.

---

## Создание Entity (сущности)

Entity — это PHP-класс, который Doctrine отображает на таблицу в базе данных.

```bash
docker compose exec php bin/console make:entity
```

Команда задаст интерактивные вопросы: имя класса, поля и их типы. Результат:

- `src/Entity/Product.php` — класс сущности
- `src/Repository/ProductRepository.php` — репозиторий для запросов

Пример готовой сущности:

```php
// src/Entity/Product.php
namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $price = null;

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getPrice(): ?float { return $this->price; }
    public function setPrice(float $price): static { $this->price = $price; return $this; }
}
```

---

## Миграции

### Создать миграцию

После изменения Entity-классов:

```bash
docker compose exec php bin/console make:migration
```

Создаёт файл `migrations/Version<timestamp>.php` с SQL-запросами.

### Применить миграции

```bash
docker compose exec php bin/console doctrine:migrations:migrate
```

### Статус миграций

```bash
docker compose exec php bin/console doctrine:migrations:status
```

### Откатить последнюю миграцию

```bash
docker compose exec php bin/console doctrine:migrations:migrate prev
```

---

## Работа с данными в контроллере

```php
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    // Создать запись
    #[Route('/product/create', methods: ['POST'])]
    public function create(): Response
    {
        $product = new Product();
        $product->setName('iPhone');
        $product->setPrice(99000.0);

        $this->em->persist($product);
        $this->em->flush();

        return $this->json(['id' => $product->getId()]);
    }

    // Получить все записи
    #[Route('/products')]
    public function list(ProductRepository $repo): Response
    {
        $products = $repo->findAll();
        return $this->render('product/list.html.twig', ['products' => $products]);
    }

    // Получить одну запись
    #[Route('/product/{id}')]
    public function show(int $id, ProductRepository $repo): Response
    {
        $product = $repo->find($id);
        if (!$product) {
            throw $this->createNotFoundException();
        }
        return $this->json(['name' => $product->getName()]);
    }
}
```

---

## Прямое подключение к PostgreSQL

```bash
# Через psql внутри контейнера
docker compose exec database psql -U app -d app

# Полезные команды psql:
\dt        # Список таблиц
\d product # Структура таблицы product
\q         # Выйти
```

Или подключитесь через GUI-клиент (DBeaver, DataGrip):

| Параметр | Значение |
|---------|---------|
| Host | `localhost` |
| Port | `5432` |
| DB | `app` |
| User | `app` |
| Password | `!ChangeMe!` |