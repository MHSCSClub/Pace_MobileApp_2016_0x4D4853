//
//  Caregiver_PatientOverview.swift
//  Mobile App 2016
//
//  Created by Jack Phillips on 3/17/16.
//  Copyright © 2016 Mamaroneck High School. All rights reserved.
//

import UIKit

class CareGiver_PatientMed: UIViewController, UITableViewDataSource, UITableViewDelegate {
    
   
    @IBOutlet var profilePic: UIImageView!
    @IBOutlet var patientName: UILabel!
    @IBOutlet var med_invitory: UILabel!
    @IBOutlet var tableView: UITableView!
    

    
    let textCellIdentifier = "TextCell"
    let dateFormatter = NSDateFormatter()
    
    var patient:Patient!
    
    
    var medicationManager = MedicationManager()
    var scheduleManager = ScheduleManager()
    
    let date = NSDate()
    let calendar = NSCalendar.currentCalendar()
    
    var components:NSDateComponents!
    
    override func viewDidLoad() {
        super.viewDidLoad()
        
        //add line seperaters
        let border = CALayer()
        let width = CGFloat(1.0)
        border.borderColor = UIColor.darkGrayColor().CGColor
        border.frame = CGRect(x: 0, y: med_invitory.frame.size.height - width, width:  med_invitory.frame.size.width, height: med_invitory.frame.size.height)
        border.borderWidth = width
        
        let topBorder = CALayer()
        topBorder.frame = CGRectMake(0, 0, med_invitory.frame.size.width, width)
        topBorder.backgroundColor = UIColor.grayColor().CGColor
        
        //med_invitory.layer.addSublayer(border)
        med_invitory.layer.addSublayer(topBorder)
        med_invitory.layer.masksToBounds = true;
        
        //table view
        tableView.delegate = self
        tableView.dataSource = self
        tableView.registerClass(UITableViewCell.self, forCellReuseIdentifier: "cell")
        tableView.rowHeight = 70;
        // Do any additional setup after loading the view.
        
        /*
        let notification = UILocalNotification()
        notification.fireDate = NSDate(timeIntervalSinceNow: 5)
        notification.alertBody = "Take Lipitor 3 Pills"
        notification.alertAction = "Take Lipitor"
        notification.soundName = "takemed.m4a"
        notification.category = "INVITE_CATEGORY";
        notification.userInfo = ["Medication": "Lipitor"]
        UIApplication.sharedApplication().scheduleLocalNotification(notification)
        */
        
        dateFormatter.dateFormat = "yyyy-MM-dd HH:mm"
        components = calendar.components([.Month, .Year, .Day],fromDate: date);
        
        
        if(patient != nil){
            patientName.text = patient.name
            medicationManager.getMeds(Constants.getAuthCode(), pid: "\(patient.pid)", completion: getschedule)
        }
        
    }
    
    
    func updateView() {
        NSOperationQueue.mainQueue().addOperationWithBlock {
            //self.medication = self.medicationManager.medications
            self.tableView.reloadData()
        }
    }
    func getschedule() {
        scheduleManager.getMedsPatient(Constants.getAuthCode(), pid: "\(patient.pid)", completion: connectMeds)
    }
    func connectMeds() {
        scheduleManager.getSceduleDate(Constants.getAuthCode(), pid: "\(patient.pid)", medManager: medicationManager, completion: updateView)
    }
    
    override func didReceiveMemoryWarning() {
        super.didReceiveMemoryWarning()
        // Dispose of any resources that can be recreated.
    }
    
    
    func numberOfSectionsInTableView(tableView: UITableView) -> Int {
        return scheduleManager.schedules.count
    }
    
    func tableView(tableView: UITableView, titleForHeaderInSection section: Int) -> String? {
        let ampm = (scheduleManager.schedules[section].hours >= 12 ? " PM" : " AM")
        let min = scheduleManager.schedules[section].minutes < 10 ? "0\(scheduleManager.schedules[section].minutes)" : "\(scheduleManager.schedules[section].minutes)"
        return "\(scheduleManager.schedules[section].hours % 12):\(min)\(ampm)"
    }
    
    func tableView(tableView: UITableView, numberOfRowsInSection section: Int) -> Int {
        return scheduleManager.schedules[section].medications.count
    }
    
    
    
    func tableView(tableView: UITableView, cellForRowAtIndexPath indexPath: NSIndexPath) -> UITableViewCell {
        let cell:UITableViewCell = UITableViewCell(style: UITableViewCellStyle.Subtitle,
            reuseIdentifier: "cell")
        
        let section = indexPath.section
        let row = indexPath.row
        
        cell.textLabel?.font = UIFont(name: "HelveticaNeue", size: 25)
        cell.textLabel?.text = "\(scheduleManager.schedules[section].medications[row].name)"
        
        cell.detailTextLabel?.font = UIFont(name: "HelveticaNeue", size: 15)
        cell.detailTextLabel?.text = "Late Taken: \(scheduleManager.schedules[section].medications[row].taken)"
        
        cell.detailTextLabel?.textColor = UIColor.blackColor()
        cell.textLabel?.textColor = UIColor.blackColor()
        
        let takeDate = dateFormatter.dateFromString("\(components.year)-\(components.month)-\(components.day) \(scheduleManager.schedules[section].hours):\(scheduleManager.schedules[section].minutes)")
        
        if (takeDate?.compare(scheduleManager.schedules[section].medications[row].taken) == NSComparisonResult.OrderedDescending){
            cell.detailTextLabel?.textColor = UIColor.whiteColor()
            cell.textLabel?.textColor = UIColor.whiteColor()
            cell.backgroundColor = UIColor(red:250/255 , green: 87/255 , blue: 87/255, alpha: 1)
        }
        
        return cell
    }
    
    @IBAction func addMed(sender: AnyObject) {
        let storyboard = UIStoryboard(name: "Main", bundle: nil)
        let vc:CreateMedViewController = (storyboard.instantiateViewControllerWithIdentifier("CreateMedViewController") as? CreateMedViewController)!
        vc.patient = patient
        self.presentViewController(vc, animated: false, completion: nil)
    }
    @IBAction func addTime(sender: AnyObject) {
        let storyboard = UIStoryboard(name: "Main", bundle: nil)
        let vc:AddScheduleViewController = (storyboard.instantiateViewControllerWithIdentifier("AddScheduleViewController") as? AddScheduleViewController)!
        vc.patient = patient
        vc.medicationList = medicationManager
        self.presentViewController(vc, animated: false, completion: nil)
    }
   
    
    /*
    // MARK: - Navigation
    
    // In a storyboard-based application, you will often want to do a little preparation before navigation
    override func prepareForSegue(segue: UIStoryboardSegue, sender: AnyObject?) {
    // Get the new view controller using segue.destinationViewController.
    // Pass the selected object to the new view controller.
    }
    */
    
}
