<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\DataTransformer\MinutesToHoursMinutesTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * A duration entered as hours + minutes, stored as a number of minutes.
 */
final class DurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('hours', IntegerType::class, [
                'label' => false,
                'constraints' => [new Range(min: 0, max: $options['max_hours'])],
                'attr' => ['min' => 0, 'max' => $options['max_hours'], 'class' => 'text-end', 'aria-label' => 'Hours'],
            ])
            ->add('minutes', IntegerType::class, [
                'label' => false,
                'constraints' => [new Range(min: 0, max: 59)],
                'attr' => ['min' => 0, 'max' => 59, 'class' => 'text-end', 'aria-label' => 'Minutes'],
            ])
            ->addModelTransformer(new MinutesToHoursMinutesTransformer());
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['row_attr']['class'] = trim(($view->vars['row_attr']['class'] ?? '').' duration-field');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'max_hours' => 23,
            'error_bubbling' => false,
        ]);
        $resolver->setAllowedTypes('max_hours', 'int');
    }

    public function getBlockPrefix(): string
    {
        return 'duration';
    }
}
