<?php
declare(strict_types=1);

namespace Plaisio\Cache\Command;

use Plaisio\Cache\Event\FlushAllCacheEvent;
use Plaisio\CompanyResolver\UniCompanyResolver;
use Plaisio\Console\Command\PlaisioCommand;
use Plaisio\Console\Command\PlaisioKernelCommand;
use SetBased\Helper\Cast;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for flushing all caches.
 */
class FlushAllCacheCommand extends PlaisioCommand
{
  //--------------------------------------------------------------------------------------------------------------------
  use PlaisioKernelCommand;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritdoc
   */
  protected function configure()
  {
    $this->setName('plaisio:cache-flush-all')
         ->setDescription('Flushes all caches for a company or all companies')
         ->addArgument('company', InputArgument::OPTIONAL, 'The abbreviation of the company')
         ->addOption('all', 'a', InputOption::VALUE_NONE, 'All companies');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes this command.
   *
   * @param InputInterface  $input  The input object.
   * @param OutputInterface $output The output object.
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $company = Cast::toOptString($input->getArgument('company'));
    $all     = Cast::toManBool($input->getOption('all'));

    if (($company===null && $all===false) || ($company!==null && $all===true))
    {
      $helper = new DescriptorHelper();
      $helper->describe($output, $this, []);

      return 0;
    }

    if ($all)
    {
      $this->forAllCompanies();

      return 0;
    }

    if ($company!==null)
    {
      $cmpId = $this->nub->DL->abcCompanyGetCmpIdByCmpAbbr($company);
      if ($cmpId===null)
      {
        $this->io->error(sprintf("Unknown company '%s'.", $company));

        return -1;
      }

      $this->forSingleCompany($cmpId);
    }

    return 0;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Flushes all caches for all companies.
   */
  private function forAllCompanies(): void
  {
    $this->nub->DL->connect();

    $companies = $this->nub->DL->abcCompanyGetAll();
    foreach ($companies as $company)
    {
      $this->forCompany($company['cmp_id']);
    }

    $this->nub->DL->disconnect();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Flushes all caches for a company.
   *
   * @param int $cmpId The ID of the company.
   */
  private function forCompany(int $cmpId): void
  {
    $this->nub->company = new UniCompanyResolver($cmpId);

    $this->nub->eventDispatcher->notify(new FlushAllCacheEvent());
    $this->nub->eventDispatcher->dispatch();
    $this->nub->DL->commit();
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Flushes all caches for a single company.
   *
   * @param string $company The abbreviation of the company.
   *
   * @return int
   */
  private function forSingleCompany(string $company): int
  {
    $ret = 0;

    $this->nub->DL->connect();

    $cmpId = $this->nub->DL->abcCompanyGetCmpIdByCmpAbbr($company);
    if ($cmpId===null)
    {
      $this->io->error(sprintf("Unknown company '%s'.", $company));

      $ret = -1;
    }
    else
    {
      $this->forCompany($cmpId);
    }

    $this->nub->DL->disconnect();

    return $ret;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
