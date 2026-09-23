<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products')]
final class ProductController extends AbstractController
{
    #[Route('', name: 'product_list', methods: ['GET'])]
    public function get_product_list(SessionInterface $session): Response
    {
        $products = $session->get('products', []);

        return $this->json(array_values($products));
    }

    #[Route('/{id}', name: 'product_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get_product_detail(int $id, SessionInterface $session): Response
    {
        $products = $session->get('products', []);

        if (!isset($products[$id])) {
            throw $this->createNotFoundException('Product not found');
        }

        return $this->json($products[$id]);
    }

    #[Route('', name: 'product_add', methods: ['POST'])]
    public function add_product(Request $request, SessionInterface $session): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['name'])) {
            return $this->json(['error' => 'Product name is required'], Response::HTTP_BAD_REQUEST);
        }

        $products = $session->get('products', []);
        $id = $products ? max(array_keys($products)) + 1 : 1;

        $new_product = [
            'id'          => $id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'price'       => $data['price'] ?? 0,
        ];

        $products[$id] = $new_product;
        $session->set('products', $products);

        return $this->json($new_product, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'product_edit', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function edit_product(int $id, Request $request, SessionInterface $session): Response
    {
        $products = $session->get('products', []);

        if (!isset($products[$id])) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['name'])) {
            return $this->json(['error' => 'Product name is required'], Response::HTTP_BAD_REQUEST);
        }

        $products[$id] = [
            'id'          => $id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'price'       => $data['price'] ?? 0,
        ];
        $session->set('products', $products);

        return $this->json($products[$id]);
    }

    #[Route('/{id}', name: 'product_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete_product(int $id, Request $request, SessionInterface $session): Response
    {
        $products = $session->get('products', []);

        if (!isset($products[$id])) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        unset($products[$id]);
        $session->set('products', $products);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
