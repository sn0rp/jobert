<?php
class Job {
    private $title;
    private $company;
    private $icon;
    private $location;
    private $remote;
    private $years_experience;
    private $description;

    public function __construct($title, $company, $icon, $location, $remote, $years_experience, $description) {
        $this->title = $title;
        $this->company = $company;
        $this->icon = $icon;
        $this->location = $location;
        $this->remote = $remote;
        $this->years_experience = $years_experience;
        $this->description = $description;
    }

    // Getter and setter methods
    public function getTitle() { return $this->title; }
    public function setTitle($title) { $this->title = $title; }

    public function getCompany() { return $this->company; }
    public function setCompany($company) { $this->company = $company; }

    public function getIcon() { return $this->icon; }
    public function setIcon($icon) { $this->icon = $icon; }

    public function getLocation() { return $this->location; }
    public function setLocation($location) { $this->location = $location; }

    public function getRemote() { return $this->remote; }
    public function setRemote($remote) { $this->remote = $remote; }

    public function getYearsExperience() { return $this->years_experience; }
    public function setYearsExperience($years_experience) { $this->years_experience = $years_experience; }

    public function getDescription() { return $this->description; }
    public function setDescription($description) { $this->description = $description; }
}