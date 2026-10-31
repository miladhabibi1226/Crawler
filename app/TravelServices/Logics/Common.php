<?php

namespace App\TravelServices\Logics;

trait Common
{
    public function signIn()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (' . $this->supplierName . ') -> SignIn', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (' . $this->supplierName . ') -> SignIn', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (' . $this->supplierName . ') -> SignIn', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . ') -> SignIn');
        return $this->done();
    }

    public function expire()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBedone ' . $this->seller->alias . ' (' . ucfirst($this->travelService) . ') -> Expire', ['Data' => $this->getErrors()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (' . ucfirst($this->travelService) . ') -> Expire', ['Data' => $this->getErrors()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . ucfirst($this->travelService) . ') -> Expire', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function ping()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->supplierName . ' -> Ping', ['Data' => $this->getErrors()]);
            return  $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->supplierName . ' -> Ping', ['Data' => $this->getErrors()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->supplierName . ' -> Ping', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function search()
    {
        // $this->fromSavedResponse = false;
        try {
            if (!$this->fromSavedResponse) {
                $this->canBeDone();
                if ($this->checkErrors()) {
                    lugError('Failed: CanBedone ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Search', ['Data' => $this->getErrors()]);
                    return $this->failed($this->errors);
                }

                $this->getContent();
                if ($this->checkErrors()) {
                    lugError('Failed: Get content ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Search', ['Data' => $this->getErrors()]);
                    return $this->failed($this->errors);
                }

                $this->extractor();
                if ($this->checkErrors()) {
                    lugError('Failed: Extractor ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Search', ['Data' => $this->getErrors()]);
                    return $this->failed($this->errors);
                }
            }
            $this->mapper();
            if ($this->checkErrors()) {
                lugError('Failed: Mapper ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Search', ['Data' => $this->getErrors()]);
                return $this->failed($this->errors);
            }

            lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Search');
            return $this->done();
        } catch (\Throwable $th) {
            lugError('Exception hppened. ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Search', ['exception' => $th->getMessage(), 'exceptionLine' => $th->getFile() . ' line: ' . $th->getLine(),  'trace' => $th->getTraceAsString()]);
            app('log')->info("pid ". app('log-system')->getPid());
            app('log')->info($th->getFile() . ' line: ' . $th->getLine());
            app('log')->info($th->getMessage());
            return $this->failed($this->errors);
        }
    }

    public function fareRules()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: - CanBeDone ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Fare Rules', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return  $this->failed();
        }
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Fare Rules', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Extractor ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Fare Rules', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Mapper ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Fare Rules', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Fare Rules');
        return $this->done();
    }


    public function targetSearch()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBedone ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Target Search', ['Data' => $this->getErrors(), 'object' => $this->object]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Target Search', ['Data' => $this->getErrors(), 'object' => $this->object]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Target Search', ['Data' => $this->getErrors(), 'object' => $this->object]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Target Search', ['Data' => $this->object]);
        return $this->done();
    }

    public function customSearch()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBedone ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> customSearch', ['Data' => $this->getErrors(), 'object' => $this->object]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> customSearch', ['Data' => $this->getErrors(), 'object' => $this->object]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> customSearch', ['Data' => $this->getErrors(), 'object' => $this->object]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> customSearch', ['Data' => $this->object]);
        return $this->done();
    }

    public function signOut()
    {
        return $this->done();
    }

    public function passengersValidation()
    {
        lugInfo('Validate ' . $this->seller->alias . ' (' . $this->supplierName . '-' . $this->travelService . ') -> Passenger Validation', ['Data' => $this->object->toArray()]);
        $this->validate();
        if ($this->checkErrors()) {
            lugError('Failed: Validate ' . $this->seller->alias . ' (' . $this->supplierName . '-' . $this->travelService . ') -> Passenger Validation', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }
        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (' . $this->supplierName . '-' . $this->travelService . ') -> Passenger Validation', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . '-' . $this->travelService . ') -> Passenger Validation', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function bookAndIssue()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: - CanBeDone ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return  $this->failed();
        }
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Extractor ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Mapper ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function book()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Book');
        return $this->done();
    }

    public function issue()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . '-' . ucfirst($this->travelService) . ') -> Issue', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function asyncTicketing()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (' . $this->supplierName . '-Flight) -> Async Ticketing', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (' . $this->supplierName . '-Flight) -> Async Ticketing', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (' . $this->supplierName . '-Flight) -> Async Ticketing', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (' . $this->supplierName . '-Flight) -> Async Ticketing', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . '-Flight) -> Async Ticketing', ['Data' => $this->object->toArray()]);
        return $this->done();
    }
}
