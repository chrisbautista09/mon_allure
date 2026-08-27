<?php

namespace App\Service;

use App\Entity\AlgorithmParameter;
use App\Repository\AlgorithmParameterRepository;

final class AlgorithmParameterService
{
    public function __construct(private readonly AlgorithmParameterRepository $repository)
    {
    }

    /** @return array<string, float> */
    public function getCurrentParameters(): array
    {
        $parameters = [];
        foreach ($this->repository->findCurrent() as $key => $parameter) {
            $value = $parameter->getParameterValue();
            if ($value !== null) {
                $parameters[$key] = $value;
            }
        }

        $this->validateParameters($parameters);

        return $parameters;
    }

    /** @param array<string, float|int> $values
     *  @return array<string, float>
     */
    public function updateParameters(array $values): array
    {
        if ($values === []) {
            return $this->getCurrentParameters();
        }

        $current = $this->getCurrentParameters();
        $this->validateParameters(array_replace($current, $values));
        $this->repository->updateParameters($values);

        return $this->getCurrentParameters();
    }

    /** @param array<string, float|int> $parameters */
    public function validateParameters(array $parameters): void
    {
        $unknownKeys = array_diff(array_keys($parameters), AlgorithmParameter::SUPPORTED_KEYS);
        if ($unknownKeys !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Paramètre algorithmique inconnu : %s.',
                implode(', ', $unknownKeys),
            ));
        }

        foreach ($parameters as $key => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new \InvalidArgumentException(sprintf('La valeur du paramètre "%s" doit être numérique.', $key));
            }

            if (!is_finite((float) $value)) {
                throw new \InvalidArgumentException(sprintf('La valeur du paramètre "%s" doit être finie.', $key));
            }

            $error = AlgorithmParameter::valueValidationError($key, (float) $value);
            if ($error !== null) {
                throw new \InvalidArgumentException($error);
            }
        }

        $this->validateOrdering($parameters);
    }

    /** @param array<string, float|int> $parameters */
    private function validateOrdering(array $parameters): void
    {
        $minimumWeeks = $parameters[AlgorithmParameter::KEY_DEFAULT_PLAN_MIN_WEEKS] ?? null;
        $maximumWeeks = $parameters[AlgorithmParameter::KEY_DEFAULT_PLAN_MAX_WEEKS] ?? null;
        if ($minimumWeeks !== null && $maximumWeeks !== null && $maximumWeeks < $minimumWeeks) {
            throw new \InvalidArgumentException('La durée maximale du plan doit être supérieure ou égale à sa durée minimale.');
        }

        $discovery = $parameters[AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY] ?? null;
        $intermediate = $parameters[AlgorithmParameter::KEY_MAX_SESSIONS_INTERMEDIATE] ?? null;
        $performance = $parameters[AlgorithmParameter::KEY_MAX_SESSIONS_PERFORMANCE] ?? null;
        if ($discovery !== null && $intermediate !== null && $discovery > $intermediate) {
            throw new \InvalidArgumentException('Le pôle Intermediate doit autoriser au moins autant de séances que Discovery.');
        }
        if ($intermediate !== null && $performance !== null && $intermediate > $performance) {
            throw new \InvalidArgumentException('Le pôle Performance doit autoriser au moins autant de séances que Intermediate.');
        }
    }
}
