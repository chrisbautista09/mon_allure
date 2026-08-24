<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/profile', name: 'api_profile_')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getProfile();

        if ($profile === null) {
            return $this->json(['message' => 'Profil physiologique introuvable.'], 404);
        }

        return $this->json($this->profileData($profile));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->authenticatedUser();

        if ($user->getProfile() !== null) {
            return $this->json(['message' => 'Un profil physiologique existe déjà.'], 409);
        }

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(['message' => 'Le corps de la requête doit contenir un JSON valide.'], 400);
        }

        $missingFields = array_values(array_filter(
            ['firstName', 'lastName', 'age'],
            static fn (string $field): bool => !array_key_exists($field, $data)
        ));

        if ($missingFields !== []) {
            return $this->json([
                'message' => 'Des champs obligatoires sont manquants.',
                'fields' => $missingFields,
            ], 422);
        }

        if (!is_string($data['firstName']) || trim($data['firstName']) === ''
            || !is_string($data['lastName']) || trim($data['lastName']) === ''
            || !is_int($data['age'])) {
            return $this->json(['message' => 'Les données du profil sont invalides.'], 422);
        }

        $typeErrors = [];

        foreach (['vma', 'vo2max'] as $field) {
            if (isset($data[$field]) && !is_int($data[$field]) && !is_float($data[$field])) {
                $typeErrors[$field][] = 'Cette valeur doit être un nombre.';
            }
        }

        foreach (['fcm', 'fcr'] as $field) {
            if (isset($data[$field]) && !is_int($data[$field])) {
                $typeErrors[$field][] = 'Cette valeur doit être un nombre entier.';
            }
        }

        if ($typeErrors !== []) {
            return $this->json([
                'message' => 'Les données du profil sont invalides.',
                'errors' => $typeErrors,
            ], 422);
        }

        $profile = (new Profile())
            ->setFirstName(trim($data['firstName']))
            ->setLastName(trim($data['lastName']))
            ->setAge($data['age'])
            ->setVma($this->nullableFloat($data, 'vma'))
            ->setVo2max($this->nullableFloat($data, 'vo2max'))
            ->setFcm($this->nullableInt($data, 'fcm'))
            ->setFcr($this->nullableInt($data, 'fcr'))
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUser($user);

        $violations = $validator->validate($profile);

        if (count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json([
                'message' => 'Les données du profil sont invalides.',
                'errors' => $errors,
            ], 422);
        }

        $user->setProfile($profile);
        $entityManager->persist($profile);
        $entityManager->flush();

        return $this->json($this->profileData($profile), 201);
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        return $user;
    }

    /** @param array<string, mixed> $data */
    private function nullableFloat(array $data, string $field): ?float
    {
        if (!isset($data[$field])) {
            return null;
        }

        return is_int($data[$field]) || is_float($data[$field])
            ? (float) $data[$field]
            : null;
    }

    /** @param array<string, mixed> $data */
    private function nullableInt(array $data, string $field): ?int
    {
        return isset($data[$field]) && is_int($data[$field]) ? $data[$field] : null;
    }

    /** @return array<string, int|float|string|null> */
    private function profileData(Profile $profile): array
    {
        return [
            'id' => $profile->getId(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'age' => $profile->getAge(),
            'vma' => $profile->getVma(),
            'vo2max' => $profile->getVo2max(),
            'fcm' => $profile->getFcm(),
            'fcr' => $profile->getFcr(),
            'updatedAt' => $profile->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
