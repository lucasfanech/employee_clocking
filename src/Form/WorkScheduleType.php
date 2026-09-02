<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WorkSchedule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WorkScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('weeklyMinutes', DurationType::class, [
                'label' => 'Hours to do in a week',
                'help' => 'Spread evenly over the five working days.',
                'max_hours' => 24 * WorkSchedule::WORKING_DAYS_PER_WEEK,
            ])
            ->add('lunchBreakMinutes', DurationType::class, [
                'label' => 'Lunch break',
                'help' => 'Minimum break counted every day. A shorter break is not credited.',
            ])
            ->add('shortLunchBreakMinutes', DurationType::class, [
                'label' => 'Short lunch break',
                'help' => 'Minimum break on the days selected below.',
            ])
            ->add('shortLunchBreakDays', ChoiceType::class, [
                'label' => 'Days with the short lunch break',
                'choices' => [
                    'Monday' => 1,
                    'Tuesday' => 2,
                    'Wednesday' => 3,
                    'Thursday' => 4,
                    'Friday' => 5,
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkSchedule::class,
        ]);
    }
}
