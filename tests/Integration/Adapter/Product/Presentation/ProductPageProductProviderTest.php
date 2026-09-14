<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Adapter\Product\Presentation;

use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;
use PrestaShop\PrestaShop\Adapter\Product\Presentation\ProductPageProductProvider;
use Product;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Integration\Utility\ContextMockerTrait;

class ProductPageProductProviderTest extends KernelTestCase
{
    use ContextMockerTrait;

    private ProductPageProductProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockContext();
        $this->provider = self::getContainer()->get(ProductPageProductProvider::class);
    }

    public function testItBuildsProductPageProductWithExplicitRequestedQuantity(): void
    {
        $product = new Product(null, false, 1);
        $product->name = 'Product page provider product';
        $product->link_rewrite = 'product-page-provider-product';
        $product->price = 12.34;
        $this->assertTrue($product->save());

        $presentedProduct = $this->provider->getProduct($product, self::getMockedContext(), null, 3);

        $this->assertInstanceOf(ProductLazyArray::class, $presentedProduct);
        $this->assertSame(3, (int) $presentedProduct['quantity_wanted']);
    }
}
