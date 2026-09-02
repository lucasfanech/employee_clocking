<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WorkDay;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WorkDayType extends AbstractType
{
    public const FIELDS = ['morningIn', 'lunchOut', 'afternoonIn', 'eveningOut'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::FIELDS as $field) {
            $builder->add($field, TimeType::class, [
                'label' => false,
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'with_seconds' => false,
                'attr' => ['class' => 'clocking-time', 'data-field' => $field],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkDay::class,
        ]);
    }
}
